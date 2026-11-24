"use client";

import { FormEvent, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Sprout, Loader2 } from "lucide-react";
import { createClient } from "@/lib/supabase/client";

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setMessage("");
    try {
      const supabase = createClient();
      const { error } = await supabase.auth.signInWithPassword({ email, password });
      if (error) throw error;
      router.replace("/");
      router.refresh();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Não foi possível entrar.");
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
          <h1 className="text-2xl font-semibold tracking-tight">Bem-vindo de volta</h1>
          <p className="mt-2 text-sm text-muted">Entre para acompanhar suas propriedades e atividades.</p>
          <form onSubmit={handleSubmit} className="mt-7 space-y-4">
            <label className="block text-sm font-medium">E-mail
              <input required type="email" autoComplete="email" value={email} onChange={e=>setEmail(e.target.value)} className="mt-2 w-full rounded-xl border border-[#dfe6e0] px-4 py-3 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100"/>
            </label>
            <label className="block text-sm font-medium">Senha
              <input required type="password" autoComplete="current-password" value={password} onChange={e=>setPassword(e.target.value)} className="mt-2 w-full rounded-xl border border-[#dfe6e0] px-4 py-3 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100"/>
            </label>
            {message && <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{message}</p>}
            <button disabled={busy} className="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 py-3 font-semibold text-white transition hover:bg-brand-800 disabled:opacity-60">
              {busy && <Loader2 size={17} className="animate-spin"/>} Entrar
            </button>
          </form>
          <p className="mt-6 text-center text-sm text-muted">Ainda não tem uma conta? <Link href="/cadastro" className="font-semibold text-brand-700 hover:underline">Criar conta</Link></p>
        </section>
        <p className="mt-6 text-center text-xs text-[#98a39b]">Plataforma de gestão e acompanhamento agrícola</p>
      </div>
    </main>
  );
}
