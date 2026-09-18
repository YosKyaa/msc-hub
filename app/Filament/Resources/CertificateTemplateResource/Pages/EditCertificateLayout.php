<?php

namespace App\Filament\Resources\CertificateTemplateResource\Pages;

use App\Filament\Resources\CertificateTemplateResource;
use App\Support\CertificateElement;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EditCertificateLayout extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CertificateTemplateResource::class;

    protected string $view = 'filament.resources.certificate-template-resource.pages.edit-certificate-layout';

    protected static ?string $title = 'Editor Visual Sertifikat';

    public array $elements = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(CertificateTemplateResource::canEdit($this->record), 403);
        $this->elements = $this->record->elements ?? [];
    }

    public function save(array $elements): void
    {
        $validated = Validator::make(['elements' => $elements], [
            'elements' => ['array', 'max:50'],
            'elements.*.label' => ['required', 'string', 'max:100'],
            'elements.*.variable' => ['required', 'in:recipient_name,recipient_role,certificate_number,event_name,event_date,organizer,signatory_name,signatory_title,verification_url,qr_code,custom_text'],
            'elements.*.text' => ['nullable', 'string', 'max:500'],
            'elements.*.x' => ['required', 'integer', 'min:0'],
            'elements.*.y' => ['required', 'integer', 'min:0'],
            'elements.*.width' => ['required', 'integer', 'min:20'],
            'elements.*.height' => ['required', 'integer', 'min:20'],
            'elements.*.font_family' => ['required', 'string', 'max:100'],
            'elements.*.font_size' => ['required', 'integer', 'min:6', 'max:200'],
            'elements.*.font_weight' => ['required', 'integer', 'in:400,600,700'],
            'elements.*.align' => ['required', Rule::in(CertificateElement::ALIGNMENTS)],
            // Perataan tegak baru ada belakangan, jadi template lama tidak
            // punya kuncinya sama sekali; CertificateElement yang memutuskan
            // bawaannya saat digambar.
            'elements.*.valign' => ['nullable', Rule::in(CertificateElement::VERTICAL_ALIGNMENTS)],
            'elements.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ])->validate();

        $this->record->update(['elements' => $validated['elements']]);
        $this->elements = $validated['elements'];
        Notification::make()->title('Posisi elemen tersimpan')->success()->send();
    }
}
