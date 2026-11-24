"use client";

import { useState } from "react";
import {
  Activity, ArrowDownRight, ArrowRight, Bell, CalendarDays, ChevronDown,
  CircleHelp, ClipboardList, CloudSun, FileText, Leaf, MapPinned,
  MoreHorizontal, Plus, Search, Sprout, Tractor, Users, Wheat
} from "lucide-react";

const nav = [
  { label: "Visão geral", icon: Activity },
  { label: "Propriedades", icon: MapPinned },
  { label: "Talhões e safras", icon: Wheat },
  { label: "Diário de campo", icon: ClipboardList },
  { label: "Visitas técnicas", icon: CalendarDays },
  { label: "Relatórios", icon: FileText }
];

const activities = [
  { title: "Visita técnica", detail: "Fazenda Santa Maria · Talhão 03", date: "Hoje, 08:40", status: "Relatório pendente", tone: "amber", icon: Users },
  { title: "Adubação de cobertura", detail: "Sítio Boa Vista · Talhão 01", date: "Ontem, 15:20", status: "Concluída", tone: "green", icon: Sprout },
  { title: "Monitoramento de pragas", detail: "Fazenda Santa Maria · Talhão 02", date: "Ontem, 10:05", status: "Em análise", tone: "blue", icon: Leaf }
];

export default function Home() {
  const [active, setActive] = useState("Visão geral");
  const [query, setQuery] = useState("");
  return (
    <main className="min-h-screen md:flex">
      <aside className="w-full border-b border-[#e7ece8] bg-white px-5 py-5 md:fixed md:inset-y-0 md:w-[252px] md:border-b-0 md:border-r md:px-5">
        <div className="mb-8 flex items-center gap-3 px-1">
          <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-700 text-white"><Sprout size={22}/></div>
          <div><div className="text-[17px] font-bold tracking-tight">Agro</div><div className="text-[11px] text-muted">GESTÃO NO CAMPO</div></div>
        </div>
        <div className="mb-3 px-3 text-[10px] font-semibold uppercase tracking-[.16em] text-[#9aa49d]">Workspace</div>
        <nav className="grid grid-cols-2 gap-1 md:block">
          {nav.map(({label, icon: Icon}) => (
            <button key={label} onClick={() => setActive(label)} className={`sidebar-link ${active === label ? "active" : ""}`}>
              <Icon size={17}/><span>{label}</span>
            </button>
          ))}
        </nav>
        <div className="mt-8 hidden rounded-2xl bg-[#f5f8f5] p-4 md:block">
          <div className="mb-2 flex items-center gap-2 text-sm font-semibold"><CircleHelp size={16} className="text-brand-700"/> Precisa de ajuda?</div>
          <p className="mb-3 text-xs leading-5 text-muted">Acesse orientações para organizar sua rotina no campo.</p>
          <button className="text-xs font-semibold text-brand-700">Central de ajuda <ArrowRight className="ml-1 inline" size={13}/></button>
        </div>
        <div className="mt-6 flex items-center gap-3 border-t border-[#edf0ed] pt-5 md:absolute md:bottom-5 md:left-5 md:right-5">
          <div className="flex h-9 w-9 items-center justify-center rounded-full bg-[#e6f1e9] text-xs font-bold text-brand-700">JS</div>
          <div className="min-w-0 flex-1"><div className="truncate text-sm font-semibold">João Silva</div><div className="text-xs text-muted">Profissional</div></div>
          <ChevronDown size={16} className="text-muted"/>
        </div>
      </aside>

      <section className="min-w-0 flex-1 md:ml-[252px]">
        <header className="flex flex-wrap items-center justify-between gap-4 border-b border-[#e7ece8] bg-white px-6 py-4 md:px-9">
          <div><div className="text-xs text-muted">Workspace / {active}</div><h1 className="mt-1 text-xl font-semibold tracking-tight">{active}</h1></div>
          <div className="flex items-center gap-3">
            <label className="hidden items-center gap-2 rounded-xl border border-[#e7ece8] px-3 py-2 sm:flex"><Search size={16} className="text-muted"/><input value={query} onChange={e=>setQuery(e.target.value)} placeholder="Buscar..." className="w-32 bg-transparent text-sm outline-none placeholder:text-[#a0aaa3]"/></label>
            <button aria-label="Notificações" className="relative rounded-xl border border-[#e7ece8] p-2.5 text-muted"><Bell size={17}/><span className="absolute right-2 top-2 h-1.5 w-1.5 rounded-full bg-emerald-500"/></button>
            <button className="flex items-center gap-2 rounded-xl bg-brand-700 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800"><Plus size={16}/> Novo registro</button>
          </div>
        </header>

        <div className="mx-auto max-w-[1400px] px-5 py-7 md:px-9 md:py-9">
          <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
            <div><p className="mb-1 text-sm text-muted">Terça-feira, 24 de novembro de 2026</p><h2 className="text-2xl font-semibold tracking-tight">Bom dia, João <span>🌱</span></h2><p className="mt-1 text-sm text-muted">Acompanhe o que está acontecendo nas suas propriedades.</p></div>
            <button className="flex items-center gap-2 rounded-xl border border-[#e1e8e2] bg-white px-3.5 py-2.5 text-sm font-medium text-[#46534a]"><CalendarDays size={16}/> Últimos 30 dias <ChevronDown size={15}/></button>
          </div>

          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {[
              {label:"Propriedades",value:"04",sub:"sob acompanhamento",icon:MapPinned,delta:"+1 este mês"},
              {label:"Talhões ativos",value:"18",sub:"em 3 propriedades",icon:Wheat,delta:"2 culturas"},
              {label:"Visitas no mês",value:"12",sub:"registros técnicos",icon:Users,delta:"+18% vs. anterior"},
              {label:"Pendências",value:"03",sub:"ações para acompanhar",icon:ClipboardList,delta:"1 prioridade"}
            ].map(({label,value,sub,icon:Icon,delta})=>(
              <div key={label} className="rounded-2xl border border-[#e7ece8] bg-white p-5 shadow-soft">
                <div className="flex items-center justify-between"><span className="text-sm text-muted">{label}</span><span className="rounded-xl bg-brand-50 p-2.5 text-brand-700"><Icon size={18}/></span></div>
                <div className="mt-4 flex items-end justify-between"><div><div className="text-3xl font-semibold tracking-tight">{value}</div><div className="mt-1 text-xs text-muted">{sub}</div></div><span className="text-[11px] font-medium text-brand-700">{delta}</span></div>
              </div>
            ))}
          </div>

          <div className="mt-6 grid gap-5 xl:grid-cols-[1.55fr_1fr]">
            <div className="rounded-2xl border border-[#e7ece8] bg-white p-5 shadow-soft md:p-6">
              <div className="mb-5 flex items-center justify-between"><div><h3 className="font-semibold">Atividade recente</h3><p className="mt-1 text-xs text-muted">Últimas movimentações nas propriedades</p></div><button className="text-xs font-semibold text-brand-700">Ver todas <ArrowRight className="ml-1 inline" size={13}/></button></div>
              <div className="divide-y divide-[#edf0ed]">
                {activities.filter(a => (a.title+a.detail).toLowerCase().includes(query.toLowerCase())).map(({title,detail,date,status,tone,icon:Icon})=>(
                  <div key={title} className="flex flex-wrap items-center gap-3 py-4 first:pt-0 last:pb-0">
                    <div className="rounded-xl bg-[#f2f6f2] p-3 text-brand-700"><Icon size={18}/></div>
                    <div className="min-w-[150px] flex-1"><div className="text-sm font-semibold">{title}</div><div className="mt-1 text-xs text-muted">{detail}</div></div>
                    <div className="text-right"><div className="text-xs text-muted">{date}</div><span className={`mt-2 inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold ${tone==="green"?"bg-emerald-50 text-emerald-700":tone==="amber"?"bg-amber-50 text-amber-700":"bg-sky-50 text-sky-700"}`}>{status}</span></div>
                  </div>
                ))}
              </div>
            </div>

            <div className="space-y-5">
              <div className="rounded-2xl border border-[#e7ece8] bg-white p-5 shadow-soft">
                <div className="mb-4 flex items-center justify-between"><div><h3 className="font-semibold">Clima na propriedade</h3><p className="mt-1 text-xs text-muted">Previsão ilustrativa · integração futura</p></div><CloudSun className="text-brand-700" size={20}/></div>
                <div className="flex items-center gap-4"><div className="text-4xl font-semibold tracking-tight">24°</div><div><div className="text-sm font-medium">Parcialmente nublado</div><div className="mt-1 text-xs text-muted">Condição demonstrativa</div></div></div>
                <div className="mt-5 grid grid-cols-3 border-t border-[#edf0ed] pt-4 text-center"><div><div className="text-xs text-muted">Umidade</div><div className="mt-1 text-sm font-semibold">68%</div></div><div><div className="text-xs text-muted">Vento</div><div className="mt-1 text-sm font-semibold">8 km/h</div></div><div><div className="text-xs text-muted">Chuva</div><div className="mt-1 text-sm font-semibold">20%</div></div></div>
              </div>
              <div className="rounded-2xl bg-brand-700 p-5 text-white shadow-soft">
                <div className="flex items-start justify-between"><div><div className="text-sm font-semibold">Organize sua próxima visita</div><p className="mt-2 max-w-[230px] text-xs leading-5 text-white/75">Registre observações, fotos e recomendações em um só lugar.</p></div><div className="rounded-xl bg-white/15 p-2.5"><CalendarDays size={19}/></div></div>
                <button className="mt-5 flex items-center gap-2 rounded-lg bg-white px-3.5 py-2.5 text-xs font-semibold text-brand-800">Agendar visita <ArrowRight size={14}/></button>
              </div>
            </div>
          </div>
          <footer className="mt-8 flex flex-wrap items-center justify-between gap-2 text-[11px] text-[#98a39b]"><span>Agro · Plataforma de gestão agrícola</span><span>Ambiente de demonstração · Dados fictícios</span></footer>
        </div>
      </section>
    </main>
  );
}
