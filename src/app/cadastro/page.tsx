"use client";

import { FormEvent, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Sprout, Loader2 } from "lucide-react";
import { createClient } from "@/lib/supabase/client";

export default function RegisterPage() {
  const router = useRouter();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [role, setRole] = useState<"professional" | "producer">("producer");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setMessage("");
    try {
      const supabase = createClient();
      const { data, error } = await supabase.auth.signUp({
        email,
        password,
        options: { data: { full_name: name, account_type: role } }
      });
      if (error) throw error;
      if (data.session) {
        router.replace("/");
        router.refresh();
      } else {
        setMessage("Cadastro recebido. Verifique seu e-mail para confirmar a conta antes de entrar.");
      }
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Não foi possível criar a conta.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-canvas px-5 py-10">
      <div className="w-full max-w-md">
        <Link href="/" className="mb-8 flex items-center justify-center gap-3">
          <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-700 text-white"><Sprout size={23}/></span>
          <span className="text-xl font-bold tracking-tight">Agro</span>
        </Link>
        <section className="rounded-2xl border border-[#e7ece8] bg-white p-7 shadow-soft sm:p-9">
          <h1 className="text-2xl font-semibold tracking-tight">Crie sua conta</h1>
          <p className="mt-2 text-sm text-muted">Comece a organizar sua rotina no campo.</p>
          <form onSubmit={handleSubmit} className="mt-7 space-y-4">
            <label className="block text-sm font-medium">Nome completo
              <input required value={name} onChange={e=>setName(e.target.value)} autoComplete="name" className="mt-2 w-full rounded-xl border border-[#dfe6e0] px-4 py-3 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"/>
            </label>
            <label className="block text-sm font-medium">E-mail
              <input required type="email" value={email} onChange={e=>setEmail(e.target.value)} autoComplete="email" className="mt-2 w-full rounded-xl border border-[#dfe6e0] px-4 py-3 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"/>
            </label>
            <label className="block text-sm font-medium">Senha
              <input required minLength={8} type="password" value={password} onChange={e=>setPassword(e.target.value)} autoComplete="new-password" className="mt-2 w-full rounded-xl border border-[#dfe6e0] px-4 py-3 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"/>
              <span className="mt-1 block text-xs text-muted">Mínimo de 8 caracteres.</span>
            </label>
            <fieldset><legend className="mb-2 text-sm font-medium">Como você vai usar o Agro?</legend>
              <div className="grid grid-cols-2 gap-3">
                <button type="button" onClick={()=>setRole("producer")} className={`rounded-xl border px-3 py-3 text-sm font-medium ${role==="producer"?"border-brand-600 bg-brand-50 text-brand-700":"border-[#dfe6e0] text-muted"}`}>Sou produtor</button>
                <button type="button" onClick={()=>setRole("professional")} className={`rounded-xl border px-3 py-3 text-sm font-medium ${role==="professional"?"border-brand-600 bg-brand-50 text-brand-700":"border-[#dfe6e0] text-muted"}`}>Sou profissional</button>
              </div>
            </fieldset>
            {message && <p role="status" className="rounded-lg bg-brand-50 p-3 text-sm text-brand-800">{message}</p>}
            <button disabled={busy} className="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 py-3 font-semibold text-white hover:bg-brand-800 disabled:opacity-60">
              {busy && <Loader2 size={17} className="animate-spin"/>} Criar conta
            </button>
          </form>
          <p className="mt-6 text-center text-sm text-muted">Já tem uma conta? <Link href="/login" className="font-semibold text-brand-700 hover:underline">Entrar</Link></p>
        </section>
      </div>
    </main>
  );
}
