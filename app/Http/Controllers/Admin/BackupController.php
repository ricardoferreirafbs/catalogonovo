<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\CatalogBackupManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(Request $request, CatalogBackupManager $manager): View
    {
        $tenant = $this->tenant($request);
        $disk = Storage::disk($manager->diskName());
        $backups = array_map(fn (string $path): array => [
            'filename' => basename($path),
            'size' => $disk->size($path),
            'modified_at' => $disk->lastModified($path),
        ], $manager->tenantFiles($tenant));

        return view('admin.backups.index', compact('backups', 'tenant'));
    }

    public function store(Request $request, CatalogBackupManager $manager): RedirectResponse
    {
        try {
            $backup = $manager->createTenant($this->tenant($request));
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors(['backup' => $exception->getMessage()]);
        }

        return back()->with('success', 'Backup da empresa criado e verificado: '.basename($backup['path'])." ({$backup['rows']} registros e {$backup['files']} arquivos).");
    }

    public function verify(Request $request, string $backup, CatalogBackupManager $manager): RedirectResponse
    {
        $tenant = $this->tenant($request);

        try {
            $path = $manager->resolveTenantFile($tenant, $backup);
            $verified = $manager->verify($path);
            if (($verified['metadata']['scope'] ?? null) !== 'tenant' || (int) ($verified['metadata']['tenant_id'] ?? 0) !== (int) $tenant->getKey()) {
                throw new RuntimeException('O arquivo não pertence a esta empresa.');
            }
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors(['backup' => $exception->getMessage()]);
        }

        return back()->with('success', "Integridade confirmada: {$verified['footer']['rows']} registros e {$verified['footer']['files']} arquivos desta empresa.");
    }

    public function download(Request $request, string $backup, CatalogBackupManager $manager): StreamedResponse
    {
        try {
            $path = $manager->resolveTenantFile($this->tenant($request), $backup);
            $verified = $manager->verify($path);
            if (($verified['metadata']['scope'] ?? null) !== 'tenant' || (int) ($verified['metadata']['tenant_id'] ?? 0) !== (int) $this->tenant($request)->getKey()) {
                abort(404);
            }
        } catch (RuntimeException) {
            abort(404);
        }

        return Storage::disk($manager->diskName())->download($path, basename($path), [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function tenant(Request $request): Tenant
    {
        return $request->user()->tenant;
    }
}
