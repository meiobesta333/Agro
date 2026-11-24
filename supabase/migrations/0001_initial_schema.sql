-- Agro initial schema
-- Execute no SQL Editor do projeto Supabase antes de habilitar usuários reais.

create extension if not exists pgcrypto;

create type public.account_type as enum ('professional', 'producer');
create type public.member_role as enum ('owner', 'admin', 'member', 'viewer');
create type public.activity_status as enum ('planned', 'in_progress', 'completed', 'cancelled');
create type public.task_status as enum ('open', 'in_progress', 'done', 'cancelled');

create table public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  full_name text not null default '',
  account_type public.account_type not null default 'producer',
  phone text,
  avatar_url text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.organizations (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  created_by uuid not null references auth.users(id),
  created_at timestamptz not null default now()
);

create table public.organization_members (
  organization_id uuid not null references public.organizations(id) on delete cascade,
  user_id uuid not null references auth.users(id) on delete cascade,
  role public.member_role not null default 'member',
  created_at timestamptz not null default now(),
  primary key (organization_id, user_id)
);

create table public.properties (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid references public.organizations(id) on delete set null,
  name text not null,
  owner_user_id uuid not null references auth.users(id),
  city text,
  state char(2),
  area_ha numeric(12,2) check (area_ha is null or area_ha >= 0),
  notes text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.property_members (
  property_id uuid not null references public.properties(id) on delete cascade,
  user_id uuid not null references auth.users(id) on delete cascade,
  role public.member_role not null default 'viewer',
  invited_by uuid references auth.users(id),
  created_at timestamptz not null default now(),
  primary key (property_id, user_id)
);

create table public.fields (
  id uuid primary key default gen_random_uuid(),
  property_id uuid not null references public.properties(id) on delete cascade,
  name text not null,
  area_ha numeric(12,2) check (area_ha is null or area_ha >= 0),
  soil_type text,
  notes text,
  created_at timestamptz not null default now()
);

create table public.crop_cycles (
  id uuid primary key default gen_random_uuid(),
  field_id uuid not null references public.fields(id) on delete cascade,
  crop_name text not null,
  cultivar text,
  season text,
  start_date date,
  end_date date,
  status text not null default 'active' check (status in ('active', 'closed', 'planned')),
  created_at timestamptz not null default now()
);

create table public.field_activities (
  id uuid primary key default gen_random_uuid(),
  field_id uuid not null references public.fields(id) on delete cascade,
  crop_cycle_id uuid references public.crop_cycles(id) on delete set null,
  created_by uuid not null references auth.users(id),
  activity_type text not null,
  description text,
  performed_at timestamptz not null default now(),
  status public.activity_status not null default 'completed',
  created_at timestamptz not null default now()
);

create table public.field_visits (
  id uuid primary key default gen_random_uuid(),
  property_id uuid not null references public.properties(id) on delete cascade,
  field_id uuid references public.fields(id) on delete set null,
  professional_id uuid not null references auth.users(id),
  producer_id uuid references auth.users(id),
  visited_at timestamptz not null default now(),
  summary text,
  observations text,
  created_at timestamptz not null default now()
);

create table public.recommendations (
  id uuid primary key default gen_random_uuid(),
  visit_id uuid not null references public.field_visits(id) on delete cascade,
  author_id uuid not null references auth.users(id),
  title text not null,
  description text not null,
  due_date date,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.tasks (
  id uuid primary key default gen_random_uuid(),
  property_id uuid not null references public.properties(id) on delete cascade,
  field_id uuid references public.fields(id) on delete set null,
  recommendation_id uuid references public.recommendations(id) on delete set null,
  assigned_to uuid references auth.users(id),
  created_by uuid not null references auth.users(id),
  title text not null,
  description text,
  due_date date,
  status public.task_status not null default 'open',
  completed_at timestamptz,
  created_at timestamptz not null default now()
);

create index properties_owner_idx on public.properties(owner_user_id);
create index property_members_user_idx on public.property_members(user_id);
create index fields_property_idx on public.fields(property_id);
create index crop_cycles_field_idx on public.crop_cycles(field_id);
create index activities_field_date_idx on public.field_activities(field_id, performed_at desc);
create index visits_property_date_idx on public.field_visits(property_id, visited_at desc);
create index tasks_property_status_idx on public.tasks(property_id, status);

-- Security-definer helpers avoid recursive RLS checks on membership tables.
create or replace function public.has_property_access(target_property uuid)
returns boolean
language sql stable security definer
set search_path = public
as $$
  select exists (
    select 1 from public.properties p
    where p.id = target_property and p.owner_user_id = auth.uid()
  ) or exists (
    select 1 from public.property_members pm
    where pm.property_id = target_property and pm.user_id = auth.uid()
  );
$$;

create or replace function public.can_manage_property(target_property uuid)
returns boolean
language sql stable security definer
set search_path = public
as $$
  select exists (
    select 1 from public.properties p
    where p.id = target_property and p.owner_user_id = auth.uid()
  ) or exists (
    select 1 from public.property_members pm
    where pm.property_id = target_property and pm.user_id = auth.uid()
      and pm.role in ('owner', 'admin')
  );
$$;

create or replace function public.has_field_access(target_field uuid)
returns boolean
language sql stable security definer
set search_path = public
as $$
  select exists (
    select 1 from public.fields f
    where f.id = target_field and public.has_property_access(f.property_id)
  );
$$;

create or replace function public.handle_new_user()
returns trigger
language plpgsql security definer
set search_path = public
as $$
begin
  insert into public.profiles (id, full_name, account_type)
  values (
    new.id,
    coalesce(new.raw_user_meta_data->>'full_name', ''),
    case when new.raw_user_meta_data->>'account_type' = 'professional'
      then 'professional'::public.account_type else 'producer'::public.account_type end
  );
  return new;
end;
$$;

create trigger on_auth_user_created
  after insert on auth.users
  for each row execute procedure public.handle_new_user();

alter table public.profiles enable row level security;
alter table public.organizations enable row level security;
alter table public.organization_members enable row level security;
alter table public.properties enable row level security;
alter table public.property_members enable row level security;
alter table public.fields enable row level security;
alter table public.crop_cycles enable row level security;
alter table public.field_activities enable row level security;
alter table public.field_visits enable row level security;
alter table public.recommendations enable row level security;
alter table public.tasks enable row level security;

create policy "profiles_select_self_or_shared_property" on public.profiles
for select to authenticated using (
  id = auth.uid() or exists (
    select 1 from public.property_members pm
    where pm.user_id = profiles.id
      and public.has_property_access(pm.property_id)
  )
);
create policy "profiles_insert_self" on public.profiles
for insert to authenticated with check (id = auth.uid());
create policy "profiles_update_self" on public.profiles
for update to authenticated using (id = auth.uid()) with check (id = auth.uid());

create policy "organizations_select_members" on public.organizations
for select to authenticated using (
  exists (select 1 from public.organization_members om
    where om.organization_id = id and om.user_id = auth.uid())
  or created_by = auth.uid()
);
create policy "organizations_insert_creator" on public.organizations
for insert to authenticated with check (created_by = auth.uid());
create policy "organizations_update_admin" on public.organizations
for update to authenticated using (
  exists (select 1 from public.organization_members om
    where om.organization_id = id and om.user_id = auth.uid() and om.role in ('owner','admin'))
) with check (created_by = auth.uid() or exists (
  select 1 from public.organization_members om
  where om.organization_id = id and om.user_id = auth.uid() and om.role in ('owner','admin')
));

create policy "organization_members_select_self_or_admin" on public.organization_members
for select to authenticated using (
  user_id = auth.uid() or exists (
    select 1 from public.organization_members own
    where own.organization_id = organization_id and own.user_id = auth.uid()
      and own.role in ('owner','admin')
  )
);
create policy "organization_members_insert_admin" on public.organization_members
for insert to authenticated with check (
  exists (select 1 from public.organization_members own
    where own.organization_id = organization_id and own.user_id = auth.uid()
      and own.role in ('owner','admin'))
  or exists (select 1 from public.organizations o
    where o.id = organization_id and o.created_by = auth.uid() and user_id = auth.uid())
);
create policy "organization_members_update_admin" on public.organization_members
for update to authenticated using (
  exists (select 1 from public.organization_members own
    where own.organization_id = organization_id and own.user_id = auth.uid()
      and own.role in ('owner','admin'))
) with check (
  exists (select 1 from public.organization_members own
    where own.organization_id = organization_id and own.user_id = auth.uid()
      and own.role in ('owner','admin'))
);
create policy "organization_members_delete_admin" on public.organization_members
for delete to authenticated using (
  exists (select 1 from public.organization_members own
    where own.organization_id = organization_id and own.user_id = auth.uid()
      and own.role in ('owner','admin'))
);

create policy "properties_select_members" on public.properties
for select to authenticated using (public.has_property_access(id));
create policy "properties_insert_owner" on public.properties
for insert to authenticated with check (owner_user_id = auth.uid());
create policy "properties_update_managers" on public.properties
for update to authenticated using (public.can_manage_property(id))
with check (public.can_manage_property(id));
create policy "properties_delete_managers" on public.properties
for delete to authenticated using (public.can_manage_property(id));

create policy "property_members_select_shared" on public.property_members
for select to authenticated using (user_id = auth.uid() or public.has_property_access(property_id));
create policy "property_members_insert_managers" on public.property_members
for insert to authenticated with check (public.can_manage_property(property_id));
create policy "property_members_update_managers" on public.property_members
for update to authenticated using (public.can_manage_property(property_id))
with check (public.can_manage_property(property_id));
create policy "property_members_delete_managers" on public.property_members
for delete to authenticated using (public.can_manage_property(property_id));

create policy "fields_select_shared" on public.fields
for select to authenticated using (public.has_property_access(property_id));
create policy "fields_insert_managers" on public.fields
for insert to authenticated with check (public.can_manage_property(property_id));
create policy "fields_update_managers" on public.fields
for update to authenticated using (public.can_manage_property(property_id))
with check (public.can_manage_property(property_id));
create policy "fields_delete_managers" on public.fields
for delete to authenticated using (public.can_manage_property(property_id));

create policy "cycles_select_shared" on public.crop_cycles
for select to authenticated using (public.has_field_access(field_id));
create policy "cycles_insert_managers" on public.crop_cycles
for insert to authenticated with check (exists (
  select 1 from public.fields f where f.id = field_id and public.can_manage_property(f.property_id)
));
create policy "cycles_update_managers" on public.crop_cycles
for update to authenticated using (exists (
  select 1 from public.fields f where f.id = field_id and public.can_manage_property(f.property_id)
)) with check (exists (
  select 1 from public.fields f where f.id = field_id and public.can_manage_property(f.property_id)
));
create policy "cycles_delete_managers" on public.crop_cycles
for delete to authenticated using (exists (
  select 1 from public.fields f where f.id = field_id and public.can_manage_property(f.property_id)
));

create policy "activities_select_shared" on public.field_activities
for select to authenticated using (public.has_field_access(field_id));
create policy "activities_insert_shared" on public.field_activities
for insert to authenticated with check (created_by = auth.uid() and public.has_field_access(field_id));
create policy "activities_update_author_or_manager" on public.field_activities
for update to authenticated using (created_by = auth.uid() or exists (
  select 1 from public.fields f where f.id = field_id and public.can_manage_property(f.property_id)
)) with check (created_by = auth.uid() or exists (
  select 1 from public.fields f where f.id = field_id and public.can_manage_property(f.property_id)
));
create policy "activities_delete_author_or_manager" on public.field_activities
for delete to authenticated using (created_by = auth.uid() or exists (
  select 1 from public.fields f where f.id = field_id and public.can_manage_property(f.property_id)
));

create policy "visits_select_shared" on public.field_visits
for select to authenticated using (public.has_property_access(property_id));
create policy "visits_insert_professional_or_manager" on public.field_visits
for insert to authenticated with check (
  professional_id = auth.uid() and public.has_property_access(property_id)
);
create policy "visits_update_author_or_manager" on public.field_visits
for update to authenticated using (professional_id = auth.uid() or public.can_manage_property(property_id))
with check (professional_id = auth.uid() or public.can_manage_property(property_id));
create policy "visits_delete_author_or_manager" on public.field_visits
for delete to authenticated using (professional_id = auth.uid() or public.can_manage_property(property_id));

create policy "recommendations_select_shared" on public.recommendations
for select to authenticated using (exists (
  select 1 from public.field_visits v where v.id = visit_id and public.has_property_access(v.property_id)
));
create policy "recommendations_insert_author" on public.recommendations
for insert to authenticated with check (author_id = auth.uid() and exists (
  select 1 from public.field_visits v where v.id = visit_id and
    (v.professional_id = auth.uid() or public.can_manage_property(v.property_id))
));
create policy "recommendations_update_author_or_manager" on public.recommendations
for update to authenticated using (author_id = auth.uid() or exists (
  select 1 from public.field_visits v where v.id = visit_id and public.can_manage_property(v.property_id)
)) with check (author_id = auth.uid() or exists (
  select 1 from public.field_visits v where v.id = visit_id and public.can_manage_property(v.property_id)
));
create policy "recommendations_delete_author_or_manager" on public.recommendations
for delete to authenticated using (author_id = auth.uid() or exists (
  select 1 from public.field_visits v where v.id = visit_id and public.can_manage_property(v.property_id)
));

create policy "tasks_select_shared" on public.tasks
for select to authenticated using (public.has_property_access(property_id));
create policy "tasks_insert_shared" on public.tasks
for insert to authenticated with check (created_by = auth.uid() and public.has_property_access(property_id));
create policy "tasks_update_shared" on public.tasks
for update to authenticated using (created_by = auth.uid() or assigned_to = auth.uid() or public.can_manage_property(property_id))
with check (created_by = auth.uid() or assigned_to = auth.uid() or public.can_manage_property(property_id));
create policy "tasks_delete_managers_or_author" on public.tasks
for delete to authenticated using (created_by = auth.uid() or public.can_manage_property(property_id));

-- Auto-update updated_at on mutable records.
create or replace function public.set_updated_at()
returns trigger language plpgsql as $$
begin new.updated_at = now(); return new; end;
$$;
create trigger profiles_updated_at before update on public.profiles
for each row execute procedure public.set_updated_at();
create trigger properties_updated_at before update on public.properties
for each row execute procedure public.set_updated_at();
create trigger recommendations_updated_at before update on public.recommendations
for each row execute procedure public.set_updated_at();
