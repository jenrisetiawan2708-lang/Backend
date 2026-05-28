<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Forum;
use Illuminate\Http\Request;

class ForumController extends Controller
{
    // GET /api/forum  - semua pesan (thread utama dengan replies)
    public function index()
    {
        $messages = Forum::with(['pengguna', 'replies.pengguna'])
            ->whereNull('parent_id')
            ->orderByDesc('tanggal')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $messages->map(fn($m) => $this->formatForum($m, true)),
        ]);
    }

    // POST /api/forum  - kirim pesan
    public function store(Request $request)
    {
        $request->validate([
            'isi_pesan' => 'required|string|max:1000',
            'parent_id' => 'nullable|integer|exists:forum,id_forum',
        ]);

        $pesan = Forum::create([
            'id_pengguna' => $request->user()->id_pengguna,
            'parent_id'   => $request->parent_id,
            'isi_pesan'   => $request->isi_pesan,
            'tanggal'     => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pesan terkirim.',
            'data'    => $this->formatForum($pesan->load('pengguna')),
        ], 201);
    }

    // DELETE /api/forum/{id}  - hapus (pemilik atau admin)
    public function destroy(Request $request, $id)
    {
        $pesan = Forum::findOrFail($id);
        $user  = $request->user();

        if ($user->role !== 'owner' && $pesan->id_pengguna !== $user->id_pengguna) {
            return response()->json(['success' => false, 'message' => 'Tidak berhak menghapus pesan ini.'], 403);
        }

        $pesan->delete();
        return response()->json(['success' => true, 'message' => 'Pesan dihapus.']);
    }

    private function formatForum(Forum $f, bool $withReplies = false): array
    {
        $data = [
            'id'          => $f->id_forum,
            'sender'      => $f->pengguna?->nama ?? 'Pengguna',
            'role'        => $f->pengguna?->role ?? 'penghuni',
            'isi_pesan'   => $f->isi_pesan,
            'tanggal'     => $f->tanggal,
            'tanggal_fmt' => \Carbon\Carbon::parse($f->tanggal)->format('H.i'),
            'parent_id'   => $f->parent_id,
        ];

        if ($withReplies && $f->relationLoaded('replies')) {
            $data['replies'] = $f->replies->map(fn($r) => $this->formatForum($r->load('pengguna')))->values();
        }

        return $data;
    }
}
