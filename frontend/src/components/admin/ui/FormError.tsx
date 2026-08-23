export function FormError({ message, fieldErrors }: { message: string | null; fieldErrors?: Record<string, string[]> }) {
  if (!message) return null;

  return (
    <div className="mb-3 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-400">
      <p>{message}</p>
      {fieldErrors ? (
        <ul className="mt-1 list-disc pl-5">
          {Object.entries(fieldErrors).map(([field, messages]) => (
            <li key={field}>{messages.join(" ")}</li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
