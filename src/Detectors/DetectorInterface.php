<?php

namespace Bale\Gupa\Detectors;

use Illuminate\Http\Request;

interface DetectorInterface
{
    /**
     * Analisa request dan return skor pelanggaran.
     * Return 0 jika tidak ada pelanggaran.
     */
    public function detect(Request $request): int;

    /**
     * Cek apakah detector ini aktif.
     */
    public function isEnabled(): bool;

    /**
     * Nama unik detector (untuk logging dan stats).
     */
    public function getName(): string;
}
