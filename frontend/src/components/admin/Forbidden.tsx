export function Forbidden({ message = "You don't have permission to view this section." }: { message?: string }) {
  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-2 p-10 text-center">
      <h1 className="text-lg font-semibold text-stone-900 dark:text-stone-100">Access denied</h1>
      <p className="max-w-sm text-sm text-stone-600 dark:text-stone-400">{message}</p>
    </div>
  );
}
