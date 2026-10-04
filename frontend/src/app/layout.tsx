import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Rifaq — Blood donation coordination",
  description:
    "A technical coordination platform for blood donors, recipients, and healthcare teams in Algeria.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="fr" className="h-full antialiased">
      <body className="min-h-full flex flex-col">{children}</body>
    </html>
  );
}
