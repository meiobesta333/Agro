import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Agro | Gestão e acompanhamento agrícola",
  description: "Plataforma colaborativa para produtores rurais e profissionais do agronegócio."
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="pt-BR">
      <body>{children}</body>
    </html>
  );
}
