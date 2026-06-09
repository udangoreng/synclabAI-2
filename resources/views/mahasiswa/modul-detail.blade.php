<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Detail Modul | Mahasiswa</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: #f0f4f8;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
        }
        .dashboard-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }
        .main-content {
            flex: 1;
            padding: 28px 32px;
            overflow-x: auto;
            width: 100%;
        }
        
        /* Responsive Grid - FIXED */
        .detail-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .left-column {
            flex: 2;
            min-width: 0;
        }
        
        .right-column {
            flex: 1;
            min-width: 320px;
        }
        
        .content-card {
            background: white;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 24px;
            overflow-x: auto;
        }
        
        /* Back button */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 24px;
            transition: all 0.3s;
        }
        
        .back-button:hover {
            transform: translateX(-4px);
            color: #4f46e5;
        }
        
        /* File preview */
        .file-preview {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }
        
        /* Buttons */
        .btn-primary {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            text-align: center;
            white-space: nowrap;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #ff9800;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            white-space: nowrap;
        }
        
        .btn-secondary:hover {
            background: #f57c00;
            transform: translateY(-2px);
        }
        
        .flashcard-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .flashcard-count {
            font-size: 48px;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .flashcard-stats,
        .file-preview,
        #pretest-box {
            overflow-x: auto;
            word-break: break-word;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                padding: 24px;
            }
            .right-column {
                min-width: 280px;
            }
        }

        @media (max-width: 640px) {
    .btn-primary, .btn-secondary {
        white-space: normal;
        word-break: keep-all;
        font-size: 14px;
        padding: 8px 12px;
    }
    
    .file-preview > div {
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    .flashcard-stats {
        padding: 16px;
    }
    
    .flashcard-count {
        font-size: 36px;
    }
}
        
        @media (max-width: 768px) {
            .detail-grid {
                flex-direction: column;
            }
            .right-column {
                min-width: 100%;
            }
            .main-content {
                padding: 20px;
            }
            .content-card {
                padding: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 16px;
            }
            .content-card {
                padding: 16px;
            }
            .left-column, .right-column {
                min-width: 100%;
            }
        }
        
        /* Loading modal */
        #loadingModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 40px;
            border-radius: 16px;
            text-align: center;
            max-width: 90%;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    @include('mahasiswa.partials.sidebar')
    
    <main class="main-content">
        <a href="{{ route('modul') }}" class="back-button">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar Modul
        </a>
        
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        
        <div class="detail-grid">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Header -->
                <div class="content-card">
                    <h2 style="margin: 0 0 8px 0; font-size: 28px;">{{ $modul->judul_modul ?? 'Modul' }}</h2>
                    <p style="color: #666; margin: 0;">
                        {{ $pertemuan->praktikum?->nama_praktikum ?? 'Praktikum' }} 
                        | Pertemuan {{ $pertemuan->pertemuan_ke }}
                    </p>
                </div>
                
                <!-- Deskripsi -->
                <div class="content-card">
                    <h3 style="margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-align-left"></i> Deskripsi Modul
                    </h3>
                    <p style="color: #4a5568; line-height: 1.6; white-space: pre-wrap;">
                        {{ $modul->deskripsi ?? 'Tidak ada deskripsi' }}
                    </p>
                </div>
                
                <!-- File Modul -->
                @if($modul->filepath)
                <div class="content-card">
                    <h3 style="margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-file-pdf"></i> File Modul
                    </h3>
                    <div class="file-preview">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                            <div>
                                <i class="fas fa-file-pdf" style="font-size: 40px; color: #ef4444;"></i>
                                <p style="margin-top: 8px; font-size: 14px; color: #666;">
                                    {{ basename($modul->filepath) }}
                                </p>
                            </div>
                            <a href="{{ asset('storage/' . $modul->filepath) }}" 
                               target="_blank"
                               class="btn-primary">
                                <i class="fas fa-download"></i> Download PDF
                            </a>
                        </div>
                    </div>
                </div>
                @endif
                
                <!-- Pretest -->
                @if($pertemuan->pretest)
                <div class="content-card">
                    <h3 style="margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-clipboard-list"></i> Pretest
                    </h3>
                    <div style="background: #e3f2fd; padding: 20px; border-radius: 12px; border-left: 4px solid #2196F3; flex-wrap: wrap; gap: 15px; display: flex; align-items: center; justify-content: space-between;">
                        <p style="margin: 0 0 8px 0; font-weight: 600; color: #1565c0; word-break: break-word;">
                            {{ $pertemuan->pretest->judul_kuis }}
                        </p>
                        <p style="margin: 0 0 16px 0; font-size: 14px; color: #666;">
                            Total Soal: <strong>{{ $pertemuan->pretest->questions->count() }}</strong>
                        </p>
                        <a href="{{ route('pretest.questions', $pertemuan->id) }}" 
                           class="btn-primary">
                            <i class="fas fa-play"></i> Mulai Pretest
                        </a>
                    </div>
                </div>
                @endif
            </div>
            
            <!-- Right Column - Flashcard -->
            <div class="right-column">
                <div class="content-card" style="position: sticky; top: 20px;">
                    <h3 style="margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-layer-group"></i> Flashcard
                    </h3>
                    
                    @if($flashcards->count() > 0)
                        <div class="flashcard-stats" style="overflow-x: auto; white-space: nowrap;">
                            <i class="fas fa-cards" style="font-size: 32px;"></i>
                            <div class="flashcard-count">{{ $flashcards->count() }}</div>
                            <p style="margin: 0; opacity: 0.9;">Total Flashcard</p>
                        </div>
                        
                        <a href="{{ route('flashcard.show', $modul->id_pertemuan) }}" 
                           class="btn-primary" 
                           style="display: block; text-align: center; margin-bottom: 12px; padding: 12px 0;">
                            <i class="fas fa-play"></i> Belajar Flashcard
                        </a>
                        
                        <button onclick="regenerateFlashcard({{ $modul->id }})" 
                                class="btn-secondary">
                            <i class="fas fa-sync-alt"></i> Regenerate Flashcard
                        </button>
                    @else
                        <div style="background: #fff3e0; padding: 30px 20px; border-radius: 12px; text-align: center;">
                            <i class="fas fa-cards" style="font-size: 48px; color: #e65100; margin-bottom: 16px; display: block;"></i>
                            <p style="margin: 0 0 16px 0; color: #e65100; font-weight: 600;">
                                Belum ada flashcard untuk modul ini
                            </p>
                            <button onclick="generateFlashcard({{ $modul->id }})" 
                                    class="btn-primary" 
                                    style="width: 100%;">
                                <i class="fas fa-magic"></i> Generate dengan AI
                            </button>
                        </div>
                    @endif
                    
                    <p style="font-size: 12px; color: #999; margin-top: 20px; text-align: center;">
                        💡 Gunakan spaced repetition untuk belajar lebih efektif
                    </p>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Loading Modal -->
<div id="loadingModal">
    <div class="modal-content">
        <div class="spinner"></div>
        <p style="font-size: 16px; color: #333; margin: 0;">Sedang generate flashcard dari AI...</p>
        <p style="font-size: 14px; color: #666; margin-top: 8px;">Mohon tunggu, proses ini mungkin memakan waktu beberapa menit</p>
    </div>
</div>

<script>
    function generateFlashcard(modulId) {
        if (!confirm('Generate flashcard akan memproses modul menggunakan AI.\nProses ini mungkin memakan waktu beberapa menit.\n\nLanjutkan?')) {
            return;
        }
        
        showLoading(true);
        
        fetch(`/mahasiswa/flashcard/${modulId}/generate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            showLoading(false);
            if (data.success) {
                alert('✅ ' + data.message);
                location.reload();
            } else {
                alert('❌ Error: ' + (data.message || 'Gagal generate flashcard'));
            }
        })
        .catch(error => {
            showLoading(false);
            console.error('Error:', error);
            alert('❌ Terjadi kesalahan: ' + error.message);
        });
    }
    
    function regenerateFlashcard(modulId) {
        if (!confirm('Regenerate akan membuat flashcard baru.\nFlashcard lama akan tetap tersimpan.\n\nLanjutkan?')) {
            return;
        }
        generateFlashcard(modulId);
    }
    
    function showLoading(show) {
        const modal = document.getElementById('loadingModal');
        modal.style.display = show ? 'flex' : 'none';
    }
    
    // Sidebar dropdown fix
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.has-sub .sub-trigger').forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                const submenu = trigger.parentElement.querySelector('.submenu');
                if (submenu) {
                    const isOpen = submenu.style.display === 'block';
                    submenu.style.display = isOpen ? 'none' : 'block';
                    const chevron = trigger.querySelector('.fa-chevron-down');
                    if (chevron) {
                        chevron.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
                        chevron.style.transition = 'transform 0.3s';
                    }
                }
            });
        });
    });
</script>
</body>
</html>