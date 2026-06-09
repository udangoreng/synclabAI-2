<?php

namespace App\Http\Controllers;

use App\Models\Praktikum;
use App\Models\Nilai;
use App\Models\Presensi;
use App\Models\Modul;
use App\Models\Pertemuan;
use App\Models\PengumpulanLaporan;
use App\Models\Pretest;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\StudentAnswer;
use App\Models\Flashcard;
use App\Models\FlashcardProgress;

class MahasiswaController extends Controller
{
    public function getMyPretest()
    {
        $user = Auth::user();

        $moduls = Modul::with(['pertemuan.praktikum'])
            ->whereHas('pertemuan.praktikum.jadwals.pendaftarans', function ($query) use ($user) {
                $query->where('id_user', $user->id);
            })
            ->get();

        $pretestData = $moduls->map(function ($modul) use ($user) {
            $pertemuan  = $modul->pertemuan;
            $praktikum  = $pertemuan?->praktikum;

            $statusAbsen = Presensi::where('id_pertemuan', $modul->id_pertemuan)
                ->where('id_user', $user->id)
                ->exists();

            // ✅ LOGIKA BARU: pretest selesai jika nilai_pretest > 0
            $nilaiPretest = Nilai::where('id_pertemuan', $modul->id_pertemuan)
                ->where('id_user', $user->id)
                ->value('nilai_pretest');
            
            $statusPretest = $nilaiPretest && $nilaiPretest > 0;

            $statusLaporan = PengumpulanLaporan::where('id_pertemuan', $modul->id_pertemuan)
                ->where('id_user', $user->id)
                ->exists();

            return [
                'id'            => $modul->id,
                'matkul'        => $praktikum?->nama_praktikum ?? 'Praktikum',
                'modul'         => $modul->judul_modul ?? 'Modul',
                'kode'          => ($praktikum?->kode_praktikum . '-M' . $pertemuan?->pertemuan_ke) ?? 'M1',
                'statusAbsen'   => $statusAbsen,
                'statusPretest' => $statusPretest,
                'statusLaporan' => $statusLaporan,
                'fileLaporan'   => null,
            ];
        })->values();

        $matkulList = $moduls
            ->pluck('pertemuan.praktikum.nama_praktikum')
            ->unique()->filter()->values();

        return view('mahasiswa.pretest', compact('pretestData', 'matkulList'));
    }

    // ─── Endpoint: Presensi / Absen ──────────────────────────────────────────

    public function absenPretest(Request $request, $modulId)
    {
        $user  = Auth::user();
        $modul = Modul::findOrFail($modulId);

        $sudahAbsen = Presensi::where('id_pertemuan', $modul->id_pertemuan)
            ->where('id_user', $user->id)
            ->exists();

        if ($sudahAbsen) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah melakukan presensi untuk pertemuan ini.'
            ], 409);
        }

        try {
            Presensi::create([
                'id_pertemuan' => $modul->id_pertemuan,
                'id_user'      => $user->id,
                'kehadiran'    => 'Hadir',
                'status'       => 'Pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Presensi berhasil dicatat!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan presensi.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // ─── Endpoint: Mulai Pretest (LOGIKA BARU) ─────────────────────────────────

    public function startPretest(Request $request, $modulId)
    {
        try {
            $user = Auth::user();
            $modul = Modul::with('pertemuan.pretest')->findOrFail($modulId);
            
            if (!$modul->pertemuan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pertemuan tidak ditemukan untuk modul ini.'
                ], 404);
            }

            $pertemuanId = $modul->id_pertemuan;

            // Cek apakah sudah presensi
            $sudahAbsen = Presensi::where('id_pertemuan', $pertemuanId)
                ->where('id_user', $user->id)
                ->exists();

            if (!$sudahAbsen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Harap lakukan presensi terlebih dahulu.'
                ], 422);
            }

            // Cek apakah pretest tersedia
            $pretest = Pretest::where('id_pertemuan', $pertemuanId)->first();
            if (!$pretest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pretest belum tersedia untuk pertemuan ini.'
                ], 404);
            }

            // ✅ LOGIKA BARU: cek apakah sudah pernah mengerjakan (nilai_pretest > 0)
            $existingNilai = Nilai::where('id_pertemuan', $pertemuanId)
                ->where('id_user', $user->id)
                ->first();

            if ($existingNilai && $existingNilai->nilai_pretest > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah mengerjakan pretest ini.'
                ], 409);
            }

            // ✅ Buat atau update record dengan nilai_pretest = 0 (sedang mengerjakan)
            if ($existingNilai) {
                $existingNilai->update([
                    'nilai_pretest' => 0,  // 0 menandakan sedang mengerjakan
                ]);
            } else {
                Nilai::create([
                    'id_pertemuan' => $pertemuanId,
                    'id_user' => $user->id,
                    'nilai_pretest' => 0,  // 0 menandakan sedang mengerjakan
                ]);
            }

            return response()->json([
                'success'  => true,
                'message'  => 'Pretest dimulai! Selamat mengerjakan.',
                'redirect' => route('pretest.questions', ['pertemuanId' => $pertemuanId])
            ]);

        } catch (\Exception $e) {
            \Log::error('StartPretest Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    // ─── Endpoint: Tampilkan Soal Pretest ─────────────────────────────────────

    public function showPretestQuestions(Request $request, $pertemuanId)
    {
        try {
            $user = Auth::user();

            $pertemuan = Pertemuan::with(['praktikum', 'modul', 'pretest.questions'])->findOrFail($pertemuanId);
            
            $sudahAbsen = Presensi::where('id_pertemuan', $pertemuanId)
                ->where('id_user', $user->id)
                ->exists();

            if (!$sudahAbsen) {
                return redirect()->route('pretest')->with('error', 'Harap lakukan presensi terlebih dahulu.');
            }

            $pretest = $pertemuan->pretest;
            if (!$pretest) {
                return redirect()->route('pretest')->with('error', 'Pretest belum tersedia.');
            }

            if ($pretest->questions->isEmpty()) {
                return redirect()->route('pretest')->with('error', 'Belum ada soal untuk pretest ini.');
            }

            // ✅ LOGIKA BARU: cek nilai_pretest > 0 berarti sudah selesai
            $nilaiPretest = Nilai::where('id_pertemuan', $pertemuanId)
                ->where('id_user', $user->id)
                ->value('nilai_pretest');

            if ($nilaiPretest && $nilaiPretest > 0) {
                return redirect()->route('pretest')->with('warning', 'Anda sudah mengerjakan pretest ini.');
            }

            $modul = $pertemuan->modul;
            $totalQuestions = $pretest->questions->count();

            return view('mahasiswa.takePretestStudent', compact('pretest', 'pertemuan', 'modul', 'totalQuestions'));

        } catch (\Exception $e) {
            \Log::error('ShowPretestQuestions Error: ' . $e->getMessage());
            return redirect()->route('pretest')->with('error', 'Gagal memuat pretest: ' . $e->getMessage());
        }
    }

    // ─── Endpoint: Submit Jawaban Pretest (LOGIKA BARU) ────────────────────────

    public function submitPretestAnswers(Request $request, $pertemuanId)
    {
        try {
            $user = Auth::user();

            $request->validate([
                'answers' => 'required|array',
                'answers.*' => 'required|in:A,B,C,D',
            ]);

            $pretest = Pretest::where('id_pertemuan', $pertemuanId)
                ->with('questions')
                ->first();

            if (!$pretest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pretest tidak ditemukan.'
                ], 404);
            }

            $answers = $request->input('answers');
            $correctCount = 0;
            $totalQuestions = $pretest->questions->count();

            if ($totalQuestions === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada soal untuk pretest ini.'
                ], 422);
            }

            foreach ($pretest->questions as $question) {
                $userAnswer = $answers[$question->id] ?? null;
                $isCorrect = false;

                if ($userAnswer) {
                    $isCorrect = strtoupper(trim($userAnswer)) === strtoupper(trim($question->correct_option));
                    if ($isCorrect) {
                        $correctCount++;
                    }
                }

                StudentAnswer::updateOrCreate(
                    [
                        'id_user'     => $user->id,
                        'id_pretest'  => $pretest->id,
                        'id_question' => $question->id,
                    ],
                    [
                        'user_answer' => $userAnswer ? strtoupper(trim($userAnswer)) : null,
                        'is_correct'  => $isCorrect,
                    ]
                );
            }

            // ✅ LOGIKA BARU: hitung skor, minimal 10 jika semua salah
            if ($correctCount > 0) {
                $score = round(($correctCount / $totalQuestions) * 100);
            } else {
                $score = 10; // Nilai effort jika semua jawaban salah
            }

            // ✅ Update nilai_pretest
            Nilai::updateOrCreate(
                ['id_pertemuan' => $pertemuanId, 'id_user' => $user->id],
                ['nilai_pretest' => $score]
            );

            return response()->json([
                'success' => true,
                'message' => 'Pretest berhasil disubmit!',
                'score'   => $score,
                'correct' => $correctCount,
                'total'   => $totalQuestions,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal: ' . $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('SubmitPretestAnswers Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan jawaban pretest: ' . $e->getMessage()
            ], 500);
        }
    }

    // ─── Sisanya sama seperti kode Anda sebelumnya (uploadLaporan, getMyHistory, dll) ───
    // ... (kode uploadLaporan, getMyHistory, getMyModul, showModul, getMyFlashcard, 
    //     showFlashcardByPertemuan, getMahasiswa, dashboard tetap sama)
    
    public function uploadLaporan(Request $request, $modulId)
    {
        $user  = Auth::user();
        $modul = Modul::findOrFail($modulId);

        $request->validate([
            'file'       => 'required|file|mimes:pdf,doc,docx,zip|max:10240',
            'keterangan' => 'nullable|string|max:500',
        ]);

        $sudahUpload = PengumpulanLaporan::where('id_pertemuan', $modul->id_pertemuan)
            ->where('id_user', $user->id)
            ->exists();

        if ($sudahUpload) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan untuk pertemuan ini sudah pernah diupload.'
            ], 409);
        }

        try {
            $path = $request->file('file')->store(
                'laporan/' . $user->id . '/' . $modul->id_pertemuan,
                'public'
            );

            PengumpulanLaporan::create([
                'id_pertemuan' => $modul->id_pertemuan,
                'id_user'      => $user->id,
                'file_path'    => $path,
                'nama_file'    => $request->file('file')->getClientOriginalName(),
                'keterangan'   => $request->input('keterangan'),
                'status'       => 'Pending',
            ]);

            return response()->json([
                'success'   => true,
                'message'   => 'Laporan berhasil diupload!',
                'file_name' => $request->file('file')->getClientOriginalName(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupload laporan.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function getMyHistory()
    {
        $user = Auth::user();

        $nilais = Nilai::where('id_user', $user->id)
            ->with('pertemuan.praktikum')
            ->get();

        $presensis = Presensi::where('id_user', $user->id)
            ->with('pertemuan.praktikum')
            ->get();

        return view('mahasiswa.riwayat', compact('nilais', 'presensis'));
    }

    public function getMyModul()
    {
        $user = Auth::user();

        $moduls = Modul::with(['pertemuan.praktikum'])
            ->whereHas('pertemuan.praktikum.jadwals.pendaftarans', function ($query) use ($user) {
                $query->where('id_user', $user->id);
            })
            ->get();

        $matkulList = $moduls
            ->pluck('pertemuan.praktikum.nama_praktikum')
            ->unique()->filter()->values();

        return view('mahasiswa.modul', compact('moduls', 'matkulList'));
    }

    public function showModul($id_pertemuan)
    {
        $user = Auth::user();
        $pertemuan = Pertemuan::with(['modul', 'praktikum'])->findOrFail($id_pertemuan);
        $modul = $pertemuan->modul;

        if (!$modul) {
            return redirect()->back()->with('error', 'Modul tidak ditemukan untuk pertemuan ini.');
        }

        $flashcards = Flashcard::where('id_modul', $modul->id)
            ->with(['progress' => function ($query) use ($user) {
                $query->where('id_user', $user->id);
            }])
            ->get();

        return view('mahasiswa.modul-detail', compact('pertemuan', 'modul', 'flashcards'));
    }

    public function getMyFlashcard()
    {
        $user = Auth::user();

        $flashcards = Flashcard::with(['modul.pertemuan.praktikum', 'progress' => function ($query) use ($user) {
            $query->where('id_user', $user->id);
        }])
            ->whereHas('modul.pertemuan.praktikum.jadwals.pendaftarans', function ($query) use ($user) {
                $query->where('id_user', $user->id);
            })
            ->get();

        $groupedFlashcards = $flashcards->groupBy(fn($card) => $card->modul?->id_pertemuan);

        return view('mahasiswa.flashcard', compact('groupedFlashcards', 'flashcards'));
    }

    public function showFlashcardByPertemuan($id_pertemuan)
    {
        $user = Auth::user();
        $pertemuan = Pertemuan::with(['modul'])->findOrFail($id_pertemuan);
        $modul = $pertemuan->modul;

        if (!$modul) {
            return redirect()->back()->with('error', 'Modul tidak ditemukan untuk pertemuan ini.');
        }

        $allFlashcards = Flashcard::where('id_modul', $modul->id)
            ->with(['progress' => function ($query) use ($user) {
                $query->where('id_user', $user->id);
            }])
            ->get();

        $flashcardsToReview = $allFlashcards->filter(function ($card) use ($user) {
            $progress = $card->progress->first();
            if (!$progress) {
                return true;
            }
            return $progress->next_review_date <= now()->toDateString();
        })->values();

        $flashcardsJson = $flashcardsToReview->map(fn($card) => [
            'id'    => $card->id,
            'front' => $card->front,
            'back'  => $card->back,
        ])->values();

        return view('mahasiswa.flashcard-detail', compact('pertemuan', 'modul', 'flashcardsToReview', 'allFlashcards', 'flashcardsJson'));
    }

    function getMahasiswa(Request $request)
    {
        $user = Auth::user();

        $jadwalIds = DB::table('pendaftaran_praktikum')
            ->where('id_user', $user->id)
            ->where('role', 'Asisten')
            ->pluck('id_jadwal')
            ->toArray();

        $praktikumIds = DB::table('jadwals')
            ->whereIn('id', $jadwalIds)
            ->pluck('id_praktikum')
            ->unique()
            ->toArray();

        $praktikums = Praktikum::whereIn('id', $praktikumIds)->get();

        $query = User::where('role', 'Praktikan')
            ->whereHas('pendaftaranPraktikums', function ($q) use ($praktikumIds, $jadwalIds) {
                $q->whereIn('id_jadwal', $jadwalIds)
                    ->where('role', 'Praktikan');
            })
            ->with(['pendaftaranPraktikums' => function ($q) use ($jadwalIds) {
                $q->whereIn('id_jadwal', $jadwalIds)
                    ->with(['jadwal.praktikum']);
            }]);

        if ($request->has('matkul') && $request->matkul) {
            $query->whereHas('pendaftaranPraktikums.jadwal.praktikum', function ($q) use ($request) {
                $q->where('nama_praktikum', $request->matkul);
            });
        }

        if ($request->has('praktikum') && $request->praktikum) {
            $query->whereHas('pendaftaranPraktikums.jadwal.praktikum', function ($q) use ($request) {
                $q->where('nama_praktikum', $request->praktikum);
            });
        }

        if ($request->has('kelas') && $request->kelas) {
            $query->whereHas('pendaftaranPraktikums.jadwal.praktikum', function ($q) use ($request) {
                $q->where('angkatan', $request->kelas);
            });
        }

        if ($request->has('pertemuan') && $request->pertemuan) {
            $query->whereHas('pendaftaranPraktikums.jadwal.pertemuan', function ($q) use ($request) {
                $q->where('pertemuan_ke', $request->pertemuan);
            });
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nomor_induk', 'like', "%{$search}%");
            });
        }

        $mahasiswas = $query->orderBy('nama', 'asc')->paginate(15);

        $praktikumNames = $praktikums->pluck('nama_praktikum')->unique()->values();
        $kelasList = $praktikums->pluck('angkatan')->unique()->sort()->values();
        $pertemuanList = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14];

        foreach ($mahasiswas as $mahasiswa) {
            $firstRegistration = $mahasiswa->pendaftaranPraktikums->first();
            if ($firstRegistration && $firstRegistration->jadwal) {
                $mahasiswa->praktikum_name = $firstRegistration->jadwal->praktikum->nama_praktikum ?? 'N/A';
                $mahasiswa->angkatan = $firstRegistration->jadwal->praktikum->angkatan ?? 'N/A';
                $latestPertemuan = $firstRegistration->jadwal->pertemuan->sortByDesc('pertemuan_ke')->first();
                $mahasiswa->pertemuan_ke = $latestPertemuan->pertemuan_ke ?? '-';
                $mahasiswa->tanggal_praktikum = $latestPertemuan->created_at ? $latestPertemuan->created_at->format('d F Y') : '-';
            } else {
                $mahasiswa->praktikum_name = 'N/A';
                $mahasiswa->angkatan = 'N/A';
                $mahasiswa->pertemuan_ke = '-';
                $mahasiswa->tanggal_praktikum = '-';
            }
        }

        return view('asisten/mahasiswa_asisten', compact('mahasiswas', 'praktikumNames', 'kelasList', 'pertemuanList'));
    }

    function dashboard()
    {
        $user = Auth::user();

        $nilais = Nilai::where('id_user', $user->id)
            ->with('pertemuan.jadwal.praktikum')
            ->get();

        $presensis = Presensi::where('id_user', $user->id)
            ->with('pertemuan.jadwal.praktikum')
            ->get();

        $praktikumCount = Praktikum::whereHas('jadwals.pertemuan.nilais', function ($query) use ($user) {
            $query->where('id_user', $user->id);
        })->count();

        $avgNilai = $nilais->avg('nilai_akhir') ?? 0;

        $hadirCount = $presensis->where('kehadiran', 'Hadir')->count();
        $totalPresensi = $presensis->count();
        $attendanceRate = $totalPresensi > 0 ? round(($hadirCount / $totalPresensi) * 100) : 0;

        $reminders = [];
        
        foreach ($nilais as $nilai) {
            if ($nilai->pertemuan && $nilai->pertemuan->jadwal) {
                $reminders[] = [
                    'praktikum' => $nilai->pertemuan->jadwal->praktikum->nama_praktikum ?? 'Praktikum',
                    'pertemuan' => $nilai->pertemuan->nama_pertemuan,
                    'modul' => $nilai->pertemuan->modul->judul_modul ?? '-',
                    'nilai' => $nilai->nilai_akhir,
                    'status' => $nilai->status,
                ];
            }
        }

        $hariBesok = now()->addDay()->format('l');
        $pretestBesok = Pertemuan::whereHas('modul')
            ->whereHas('jadwal', function ($q) use ($hariBesok) {
                $q->where('hari', $hariBesok);
            })
            ->whereHas('jadwal.pendaftarans', function ($q) use ($user) {
                $q->where('id_user', $user->id);
            })
            ->with('praktikum', 'jadwal')
            ->get();

        foreach ($pretestBesok as $pertemuan) {
            $jadwal = $pertemuan->jadwal;
            $reminders[] = [
                'praktikum' => $pertemuan->praktikum->nama_praktikum ?? '-',
                'pertemuan' => $pertemuan->nama_pertemuan ?? '-',
                'modul'     => ($jadwal?->jam_mulai ?? '-') . ' WIB',
                'nilai'     => '-',
                'status'    => 'Pretest Besok',
            ];
        }

        $hariIni = now()->format('l');
        $jadwalHariIni = Pertemuan::whereHas('jadwal', function ($q) use ($hariIni) {
                $q->where('hari', $hariIni);
            })
            ->whereHas('jadwal.pendaftarans', function ($q) use ($user) {
                $q->where('id_user', $user->id);
            })
            ->with('praktikum', 'jadwal')
            ->get();

        $nilaiPerPertemuan = $nilais->map(function ($nilai) {
            return [
                'praktikum'     => $nilai->pertemuan?->praktikum?->nama_praktikum ?? '-',
                'pertemuan'     => $nilai->pertemuan?->nama_pertemuan ?? '-',
                'modul'         => $nilai->pertemuan?->modul?->judul_modul ?? '-',
                'nilai_pretest' => $nilai->nilai_pretest,
                'nilai_laporan' => $nilai->nilai_laporan,
                'nilai_total'   => $nilai->nilai_total,
                'nilai_akhir'   => $nilai->nilai_akhir,
                'status'        => $nilai->status,
            ];
        });

        return view('mahasiswa.dashboard', compact(
            'user',
            'praktikumCount',
            'nilais',
            'presensis',
            'avgNilai',
            'attendanceRate',
            'reminders',
            'nilaiPerPertemuan',
            'jadwalHariIni'
        ));
    }
}