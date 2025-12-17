<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    public function getHeading(): string|Htmlable
    {
        return 'Selamat Datang';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Masuk ke panel administrasi MSC Hub';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Html::make($this->getAlertHtml()),
                Html::make($this->getGoogleButtonHtml()),
                Html::make($this->getDividerHtml()),
                $this->getFormContentComponent(),
                $this->getMultiFactorChallengeFormContentComponent(),
            ]);
    }

    protected function getAlertHtml(): string
    {
        $error = session('error');
        $success = session('success');

        if ($error) {
            return '
                <div style="border-radius:8px;border:1px solid #fecaca;background-color:#fef2f2;padding:12px;margin-bottom:8px;">
                    <div style="display:flex;align-items:flex-start;gap:10px;">
                        <svg style="height:20px;width:20px;flex-shrink:0;color:#ef4444;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                        </svg>
                        <p style="font-size:14px;font-weight:500;color:#b91c1c;">' . e($error) . '</p>
                    </div>
                </div>
            ';
        }

        if ($success) {
            return '
                <div style="border-radius:8px;border:1px solid #bbf7d0;background-color:#f0fdf4;padding:12px;margin-bottom:8px;">
                    <div style="display:flex;align-items:flex-start;gap:10px;">
                        <svg style="height:20px;width:20px;flex-shrink:0;color:#22c55e;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                        </svg>
                        <p style="font-size:14px;font-weight:500;color:#15803d;">' . e($success) . '</p>
                    </div>
                </div>
            ';
        }

        return '';
    }

    protected function getGoogleButtonHtml(): string
    {
        $url = route('admin.google.redirect');

        return '
            <a href="' . $url . '" 
               style="display:flex;width:100%;align-items:center;justify-content:center;gap:12px;border-radius:8px;background-color:#111827;padding:12px 20px;font-size:14px;font-weight:500;color:#ffffff;text-decoration:none;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);transition:all 0.2s;"
               onmouseover="this.style.backgroundColor=\'#1f2937\'"
               onmouseout="this.style.backgroundColor=\'#111827\'">
                <svg style="height:20px;width:20px;" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                <span>Masuk dengan Google</span>
            </a>
            <p style="margin-top:12px;text-align:center;font-size:12px;color:#6b7280;">
                Gunakan email <span style="font-weight:600;color:#d97706;">@jgu.ac.id</span>
            </p>
        ';
    }

    protected function getDividerHtml(): string
    {
        return '
            <div style="position:relative;margin:24px 0;">
                <div style="position:absolute;inset:0;display:flex;align-items:center;">
                    <div style="width:100%;border-top:1px solid #e5e7eb;"></div>
                </div>
                <div style="position:relative;display:flex;justify-content:center;">
                    <span style="background-color:#ffffff;padding:0 16px;font-size:12px;font-weight:500;text-transform:uppercase;letter-spacing:0.05em;color:#9ca3af;">
                        atau
                    </span>
                </div>
            </div>
        ';
    }
}
