import type { Metadata } from "next";
import { CustomerRegisterForm } from "@/components/account/RegisterForm";
import { Container } from "@/components/ui/Container";

export const metadata: Metadata = { title: "Create Account" };

export default function AccountRegisterPage() {
  return (
    <main className="flex flex-1 flex-col items-center justify-center gap-6 py-12">
      <Container className="flex flex-col items-center gap-6">
        <h1 className="text-xl font-semibold text-stone-900 dark:text-stone-100">Create your account</h1>
        <CustomerRegisterForm />
      </Container>
    </main>
  );
}
