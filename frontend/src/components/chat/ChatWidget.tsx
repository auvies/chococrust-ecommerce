"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useCustomerAuth } from "@/components/account/AuthProvider";
import { createConversation, getMyConversations, sendChatMessage, isRateLimited } from "@/lib/api/chat";
import { ApiError } from "@/lib/api/client";
import type { ChatMessage } from "@/types/api";

/**
 * The customer-facing chatbot widget. Chat is gated behind a customer
 * account (matching ChatController's own requirement - a conversation
 * always belongs to a Customer) rather than expanding auth to cover
 * anonymous visitors, which would be a separate, larger change this phase
 * doesn't ask for.
 *
 * A conversation is created lazily on the first message, not just from
 * opening the widget - so browsing customers who never actually chat don't
 * leave behind empty conversation rows.
 */
export function ChatWidget() {
  const { user, loading: authLoading } = useCustomerAuth();
  const [open, setOpen] = useState(false);

  if (authLoading) return null;

  return (
    <div className="fixed bottom-4 right-4 z-50 sm:bottom-6 sm:right-6">
      {open ? (
        <ChatPanel loggedIn={!!user} onClose={() => setOpen(false)} />
      ) : (
        <button
          type="button"
          onClick={() => setOpen(true)}
          aria-label="Open chat"
          className="flex h-14 w-14 items-center justify-center rounded-full bg-amber-700 text-white shadow-lg hover:bg-amber-800"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} className="h-6 w-6">
            <path strokeLinecap="round" strokeLinejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 0 1-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8Z" />
          </svg>
        </button>
      )}
    </div>
  );
}

function ChatPanel({ loggedIn, onClose }: { loggedIn: boolean; onClose: () => void }) {
  const [conversationId, setConversationId] = useState<number | null>(null);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [draft, setDraft] = useState("");
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [loadingExisting, setLoadingExisting] = useState(loggedIn);
  const listRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!loggedIn) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- unavoidable for a "loading" flag on a fetch-on-mount that has nothing to fetch
      setLoadingExisting(false);
      return;
    }
    getMyConversations({ filter: { status: "open" }, sort: "-started_at", per_page: 1 })
      .then((page) => {
        const existing = page.data[0];
        if (existing) {
          setConversationId(existing.id);
          setMessages(existing.messages);
        }
      })
      .finally(() => setLoadingExisting(false));
  }, [loggedIn]);

  useEffect(() => {
    listRef.current?.scrollTo({ top: listRef.current.scrollHeight });
  }, [messages]);

  async function handleSend() {
    const content = draft.trim();
    if (!content || sending) return;

    setSending(true);
    setError(null);
    setDraft("");

    try {
      let id = conversationId;
      if (!id) {
        const conversation = await createConversation();
        id = conversation.id;
        setConversationId(id);
      }

      const result = await sendChatMessage(id, content);
      setMessages((prev) => [...prev, result.message, ...(result.reply ? [result.reply] : [])]);
    } catch (err) {
      if (isRateLimited(err)) {
        setError("You're sending messages a little too fast - please wait a moment and try again.");
      } else if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Something went wrong sending that message.");
      }
      setDraft(content);
    } finally {
      setSending(false);
    }
  }

  return (
    <div className="flex h-[28rem] w-[22rem] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-lg border border-stone-200 bg-white shadow-xl dark:border-stone-800 dark:bg-stone-950">
      <div className="flex items-center justify-between border-b border-stone-200 bg-amber-700 px-4 py-3 text-white dark:border-stone-800">
        <span className="text-sm font-semibold">Choco Crust Support</span>
        <button type="button" onClick={onClose} aria-label="Close chat" className="text-white/90 hover:text-white">
          ✕
        </button>
      </div>

      {!loggedIn ? (
        <div className="flex flex-1 flex-col items-center justify-center gap-3 p-6 text-center text-sm text-stone-600 dark:text-stone-400">
          <p>Log in to chat with us about products, orders, and delivery.</p>
          <Link href="/account/login" className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white dark:bg-amber-700">
            Log in
          </Link>
        </div>
      ) : loadingExisting ? (
        <div className="flex flex-1 items-center justify-center text-sm text-stone-500">Loading…</div>
      ) : (
        <>
          <div ref={listRef} className="flex-1 space-y-3 overflow-y-auto p-4">
            {messages.length === 0 ? (
              <p className="text-sm text-stone-500">
                Ask about prices, stock, delivery, payment methods, your order status, or anything else - we&apos;re here to help.
              </p>
            ) : (
              messages.map((message) => (
                <div key={message.id} className={message.sender_type === "customer" ? "flex justify-end" : "flex justify-start"}>
                  <div
                    className={`max-w-[85%] rounded-lg px-3 py-2 text-sm ${
                      message.sender_type === "customer"
                        ? "bg-amber-700 text-white"
                        : "bg-stone-100 text-stone-800 dark:bg-stone-900 dark:text-stone-200"
                    }`}
                  >
                    {message.content}
                  </div>
                </div>
              ))
            )}
            {sending ? <p className="text-xs text-stone-400">Sending…</p> : null}
          </div>

          {error ? <p className="border-t border-stone-200 px-4 py-2 text-xs text-red-600 dark:border-stone-800 dark:text-red-400">{error}</p> : null}

          <form
            onSubmit={(e) => {
              e.preventDefault();
              handleSend();
            }}
            className="flex gap-2 border-t border-stone-200 p-3 dark:border-stone-800"
          >
            <input
              type="text"
              value={draft}
              onChange={(e) => setDraft(e.target.value)}
              placeholder="Type a message…"
              maxLength={4000}
              disabled={sending}
              className="flex-1 rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100"
            />
            <button
              type="submit"
              disabled={sending || !draft.trim()}
              className="rounded-md bg-stone-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700"
            >
              Send
            </button>
          </form>
        </>
      )}
    </div>
  );
}
