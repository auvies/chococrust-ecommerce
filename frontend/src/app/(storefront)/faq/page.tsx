import type { Metadata } from "next";
import { Container } from "@/components/ui/Container";

export const metadata: Metadata = {
  title: "FAQ",
  description: "Frequently asked questions about ordering from Choco Crust.",
};

const FAQS: { question: string; answer: string }[] = [
  {
    question: "How can I pay for my order?",
    answer:
      "Cash on Delivery (COD) is our baseline payment method. Additional online payment options may be added later.",
  },
  {
    question: "Do you deliver locally and nationwide?",
    answer:
      "We offer local same-city delivery as well as shipping across Pakistan for shelf-stable items. Exact coverage, fees, and delivery windows are shown at checkout once available.",
  },
  {
    question: "How do I track my order?",
    answer: "Order tracking and account pages are coming in a future update — stay tuned.",
  },
  {
    question: "Can I leave a review for a product?",
    answer:
      "Yes — once you're signed in, you can leave a review on any product's page. Verified purchases are marked accordingly.",
  },
  {
    question: "How do I get in touch with support?",
    answer: "Visit the Contact page for our current phone, email, and address details.",
  },
];

export default function FaqPage() {
  return (
    <main className="flex-1 py-10 sm:py-16">
      <Container className="flex max-w-2xl flex-col gap-6">
        <h1 className="text-2xl font-semibold tracking-tight text-stone-900 dark:text-stone-100 sm:text-3xl">
          Frequently Asked Questions
        </h1>
        <dl className="flex flex-col divide-y divide-stone-200 dark:divide-stone-800">
          {FAQS.map((faq) => (
            <div key={faq.question} className="py-4">
              <dt className="text-sm font-semibold text-stone-900 dark:text-stone-100">{faq.question}</dt>
              <dd className="mt-1 text-sm text-stone-600 dark:text-stone-400">{faq.answer}</dd>
            </div>
          ))}
        </dl>
      </Container>
    </main>
  );
}
