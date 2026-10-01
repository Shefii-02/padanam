<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Core\Http\Controller;
use App\Models\Invoice;
use App\Modules\Commerce\Services\InvoiceService;
use Illuminate\Support\Facades\Storage;

/** Shareable invoice link (WhatsApp) – long random token, no login. */
class PublicInvoiceController extends Controller
{
    public function show(string $token, InvoiceService $invoices)
    {
        $inv = Invoice::where('public_token', $token)->firstOrFail();
        if (! $inv->pdf_path || ! Storage::disk('local')->exists($inv->pdf_path)) {
            $invoices->renderPdf($inv);
            $inv->refresh();
        }

        return response(Storage::disk('local')->get($inv->pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$inv->invoice_no.'.pdf"',
        ]);
    }
}
