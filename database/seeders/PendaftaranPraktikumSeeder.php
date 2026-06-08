<?php

namespace Database\Seeders;

use App\Models\PendaftaranPraktikum;
use App\Models\Jadwal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PendaftaranPraktikumSeeder extends Seeder
{
    public function run(): void
    {
        $praktikans = User::where('role', 'Praktikan')->get();
        $asistens = User::where('role', 'Asisten')->get();

        if ($praktikans->isEmpty()) {
            $this->command->warn("Data Praktikan kosong. Seeder dilewati.");
            return;
        }

        $now = Carbon::now();
        $pendaftarans = [];

        // Mapping praktikan ke jadwal yang diinginkan
        // Format: [nama_praktikan => [kode_praktikum => kondisi_jadwal]]
        // Kondisi: hari, jam_mulai
        $jadwalMapping = [
            'Eri Sepuh' => [
                'PD2401' => ['hari' => 'Selasa', 'jam_mulai' => '09:30'],
                'SD2402' => ['hari' => 'Rabu', 'jam_mulai' => '11:00'],
                'BD2403' => ['hari' => 'Senin', 'jam_mulai' => '08:00'],
            ],
            'Rizka Aulia Putri' => [
                'PD2401' => ['hari' => 'Selasa', 'jam_mulai' => '09:30'],
                'SD2402' => ['hari' => 'Rabu', 'jam_mulai' => '11:00'],
                'BD2403' => ['hari' => 'Senin', 'jam_mulai' => '08:00'],
            ],
            'Doni Setiawan' => [
                'PD2401' => ['hari' => 'Selasa', 'jam_mulai' => '09:30'],
                'SD2402' => ['hari' => 'Rabu', 'jam_mulai' => '11:00'],
                'BD2403' => ['hari' => 'Senin', 'jam_mulai' => '08:00'],
            ],
        ];

        foreach ($praktikans as $praktikan) {
            $namaPraktikan = $praktikan->nama;

            if (!isset($jadwalMapping[$namaPraktikan])) {
                continue;
            }

            foreach ($jadwalMapping[$namaPraktikan] as $kodePraktikum => $kondisi) {
                // Cari jadwal yang sesuai
                $jadwal = Jadwal::whereHas('praktikum', function ($query) use ($kodePraktikum) {
                    $query->where('kode_praktikum', $kodePraktikum);
                })
                    ->where('hari', $kondisi['hari'])
                    ->where('jam_mulai', $kondisi['jam_mulai'])
                    ->first();

                if ($jadwal) {
                    $pendaftarans[] = [
                        'id_jadwal' => $jadwal->id,
                        'id_user' => $praktikan->id,
                        'role' => 'Praktikan',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                } else {
                    $this->command->warn("Jadwal tidak ditemukan untuk {$kodePraktikum} - {$kondisi['hari']} {$kondisi['jam_mulai']}");
                }
            }
        }

        // Daftarkan asisten (opsional, tidak mengganggu)
        if ($asistens->isNotEmpty()) {
            $allJadwals = Jadwal::all();
            $assignedAsisten = collect();

            foreach ($allJadwals as $jadwal) {
                $available = $asistens->diff($assignedAsisten);
                if ($available->isEmpty()) break;

                $pick = $available->random(min(1, $available->count()));
                foreach ($pick as $asisten) {
                    $pendaftarans[] = [
                        'id_jadwal' => $jadwal->id,
                        'id_user' => $asisten->id,
                        'role' => 'Asisten',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $assignedAsisten->push($asisten);
                }
            }
        }

        // Insert data
        foreach ($pendaftarans as $pendaftaran) {
            PendaftaranPraktikum::updateOrCreate(
                [
                    'id_jadwal' => $pendaftaran['id_jadwal'],
                    'id_user' => $pendaftaran['id_user'],
                ],
                [
                    'role' => $pendaftaran['role'],
                ]
            );
        }

        $this->command->info("Pendaftaran praktikum berhasil: " . count($pendaftarans) . " record.");
    }
}