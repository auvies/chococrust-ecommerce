<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The single place an uploaded file becomes a stored, public URL (CLAUDE.md
 * §12) — used by every upload endpoint (product media, hero banner
 * desktop/mobile images, category images) so "safe filename, correct disk,
 * correct visibility" is enforced once, not reimplemented per module.
 *
 * This only stores the file — it does NOT validate it. Validation (genuine
 * image data via `image`, a server-side MIME allow-list via `mimes:...`,
 * and a size cap via `max:...`) stays in each caller's own Form Request,
 * since the allow-list/size limit is a per-context business decision (e.g.
 * a hero banner might reasonably allow a larger file than a thumbnail) —
 * this service would otherwise have to know about every caller's rules.
 */
class MediaUploadService
{
    /** @return string the public URL the file is now reachable at */
    public function store(UploadedFile $file, string $directory): string
    {
        $disk = config('filesystems.default');

        // Never trust or reuse the client-supplied filename (CLAUDE.md
        // §12) - a generated UUID is the only thing that ever reaches disk,
        // regardless of what the browser sent (including path-traversal
        // attempts like "../../etc/passwd.jpg").
        //
        // Security fix (SECURITY_AUDIT.md): the extension used to come from
        // getClientOriginalExtension() - literally the extension off the
        // attacker-supplied original filename, not the actual file content.
        // Every caller's Form Request validates the *content* as a genuine
        // image (`image`/`mimes:...`), but a file whose content passes that
        // check while its original name was e.g. "shell.php" would still be
        // stored as "{uuid}.php". extension() derives the extension from
        // the server-detected MIME type instead - the same thing that was
        // actually validated. Falls back to a fixed safe extension in the
        // (validation-should-make-this-unreachable) case content-sniffing
        // can't determine one, rather than ever falling back to client input.
        $filename = Str::uuid()->toString().'.'.($file->extension() ?: 'bin');
        $path = $file->storeAs($directory, $filename, $disk);

        return Storage::disk($disk)->url($path);
    }
}
