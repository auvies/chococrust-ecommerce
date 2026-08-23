"use client";

import Link from "next/link";
import { useState } from "react";
import type { HeroBanner } from "@/types/api";

export function Hero({ banners }: { banners: HeroBanner[] }) {
  const [index, setIndex] = useState(0);

  if (banners.length === 0) return null;

  const banner = banners[index % banners.length];

  return (
    <section aria-label="Featured promotions" className="relative">
      <div className="relative flex min-h-[45vh] w-full flex-col justify-end overflow-hidden bg-stone-800 p-6 text-white sm:min-h-[55vh] sm:p-10">
        {banner.image_url ? (
          <>
            {/* eslint-disable-next-line @next/next/no-img-element -- desktop/mobile crops come from an admin-uploaded or external URL, not a static local asset */}
            <img
              src={banner.mobile_image_url ?? banner.image_url}
              alt={banner.alt_text ?? banner.title}
              className="absolute inset-0 h-full w-full object-cover sm:hidden"
            />
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src={banner.image_url}
              alt={banner.alt_text ?? banner.title}
              className="absolute inset-0 hidden h-full w-full object-cover sm:block"
            />
          </>
        ) : null}
        <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent" />
        <div className="relative max-w-lg">
          <h1 className="text-2xl font-semibold tracking-tight sm:text-4xl">{banner.title}</h1>
          {banner.subtitle ? <p className="mt-2 text-sm text-white/90 sm:text-base">{banner.subtitle}</p> : null}
          {banner.cta_text && banner.link_url ? (
            <Link
              href={banner.link_url}
              className="mt-4 inline-block rounded-md bg-amber-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-amber-700"
            >
              {banner.cta_text}
            </Link>
          ) : null}
        </div>
      </div>

      {banners.length > 1 ? (
        <div className="absolute bottom-4 left-1/2 flex -translate-x-1/2 gap-2">
          {banners.map((b, i) => (
            <button
              key={b.id}
              type="button"
              onClick={() => setIndex(i)}
              aria-label={`Show promotion ${i + 1}`}
              aria-current={i === index}
              className={`h-2 w-2 rounded-full ${i === index ? "bg-white" : "bg-white/40"}`}
            />
          ))}
        </div>
      ) : null}
    </section>
  );
}
