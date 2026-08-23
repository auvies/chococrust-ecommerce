import { Suspense } from "react";
import type { Metadata } from "next";
import { CustomerLoginForm } from "@/components/account/LoginForm";
import { Container } from "@/components/ui/Container";

export const metadata: Metadata = { title: "Sign In" };

export default function AccountLoginPage() {
  return (
    <main className="flex flex-1 flex-col items-center justify-center gap-6 py-12">
      <Container className="flex flex-col items-center gap-6">
        <h1 className="text-xl font-semibold text-stone-900 dark:text-stone-100">Sign in to your account</h1>
        <Suspense fallback={null}>
          <CustomerLoginForm />
        </Suspense>
      </Container>
    </main>
  );
}
