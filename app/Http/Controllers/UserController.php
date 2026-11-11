<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\AuthController as ApiAuthController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Menampilkan daftar user.
     */
   public function index(Request $request)
{
    $keyword = $request->keyword;

    $users = User::with([
        'rooms' => fn ($query) => $query->select('id', 'pic_id', 'room_name')->orderBy('room_name'),
    ])
    ->when($keyword, function ($query) use ($keyword) {
        $query->where(function ($subQuery) use ($keyword) {
            $subQuery->where('name', 'like', "%{$keyword}%")
                     ->orWhere('email', 'like', "%{$keyword}%");
        });
    })
    ->orderBy('name')
    ->paginate(10);

    return view('admin.users.index', compact('users', 'keyword'));
}
    /**
     * Menampilkan form tambah user.
     */
    public function create()
    {
        return view('admin.users.create');
    }

    /**
     * Menyimpan user baru.
     */
    public function store(Request $request)
    {
        $canHaveWhatsApp = User::canHaveWhatsAppPhone($request->role);

        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => 'required|in:admin,pic,guru,siswa',
            'phone' => $canHaveWhatsApp ? $this->whatsAppPhoneValidationRules() : 'nullable',
        ], $this->whatsAppValidationMessages());

        $phone = $this->resolveNormalizedWhatsAppPhone($request, $canHaveWhatsApp);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => $request->role,
            'phone' => $phone,
        ]);

        return redirect()->route('users.index')
                         ->with('success', 'User berhasil ditambahkan.');
    }

    /**
     * Menampilkan detail user.
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Menampilkan form edit user.
     */
    public function edit(User $user)
    {
        $user->loadMissing([
            'rooms' => fn ($query) => $query->select('id', 'pic_id', 'room_name')->orderBy('room_name'),
        ]);

        return view('admin.users.edit', compact('user'));
    }

    /**
     * Mengupdate user.
     */
    public function update(Request $request, User $user)
    {
        $canHaveWhatsApp = User::canHaveWhatsAppPhone($request->role);

        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:admin,pic,guru,siswa',
            'phone' => $canHaveWhatsApp ? $this->whatsAppPhoneValidationRules() : 'nullable',
        ], $this->whatsAppValidationMessages());

        $assignedRooms = $user->rooms()
            ->orderBy('room_name')
            ->pluck('room_name');

        if ($assignedRooms->isNotEmpty() && $request->role !== 'pic') {
            $roomList = $assignedRooms->implode(', ');

            return back()
                ->withInput()
                ->withErrors([
                    'role' => "Role PIC tidak dapat diubah karena masih memiliki tanggung jawab ruangan ({$roomList}). Silakan ubah atau lepaskan penanggung jawab ruangan terlebih dahulu.",
                ]);
        }

        $phone = $this->resolveNormalizedWhatsAppPhone($request, $canHaveWhatsApp);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'phone' => $phone,
        ];

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }

        $user->update($data);

        return redirect()->route('users.index')
                         ->with('success', 'Data user berhasil diperbarui.');
    }

    /**
     * Normalisasi nomor WhatsApp di server sebelum disimpan ke kolom users.phone (VARCHAR 20).
     */
    private function resolveNormalizedWhatsAppPhone(Request $request, bool $canHaveWhatsApp): ?string
    {
        if (!$canHaveWhatsApp) {
            return null;
        }

        $normalized = User::normalizeWhatsAppPhone((string) $request->input('phone', ''));
        if ($normalized === null || strlen($normalized) > 20) {
            return null;
        }

        return $normalized;
    }

    /**
     * Aturan validasi nomor WhatsApp untuk role Guru / Organisasi dan PIC.
     * Input mentah boleh memakai pemisah hingga 25 karakter sebelum dinormalisasi ke 628... (10-15 digit).
     *
     * @return array<int, mixed>
     */
    private function whatsAppPhoneValidationRules(): array
    {
        return [
            'nullable',
            'string',
            'max:25',
            function (string $attribute, mixed $value, \Closure $fail): void {
                $raw = trim((string) $value);
                if ($raw !== '' && User::normalizeWhatsAppPhone($raw) === null) {
                    $fail('Format nomor WhatsApp tidak valid atau terlalu panjang. Gunakan format 08..., 628..., atau +628... (10-15 digit).');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    private function whatsAppValidationMessages(): array
    {
        return [
            'phone.string' => 'Nomor WhatsApp harus berupa teks.',
            'phone.max' => 'Nomor WhatsApp maksimal 25 karakter sebelum normalisasi.',
        ];
    }

    /**
     * Menghapus user.
     */
   public function destroy(User $user)
{
    $blockedRooms = DB::transaction(function () use ($user) {
        $lockedUser = User::whereKey($user->id)->lockForUpdate()->first();
        if (!$lockedUser) {
            return collect();
        }

        $assignedRooms = $lockedUser->rooms()
            ->lockForUpdate()
            ->orderBy('room_name')
            ->pluck('room_name');

        if ($assignedRooms->isNotEmpty()) {
            return $assignedRooms;
        }

        $lockedUser->delete();

        return collect();
    });

    if ($blockedRooms->isNotEmpty()) {
        $roomList = $blockedRooms->implode(', ');
        $subject = $user->role === 'pic' ? 'PIC' : 'User';

        return redirect()
            ->route('users.index')
            ->with(
                'error',
                "{$subject} tidak dapat dihapus karena masih memiliki tanggung jawab ruangan ({$roomList}). Silakan ubah atau lepaskan penanggung jawab ruangan terlebih dahulu."
            );
    }

    ApiAuthController::revokeAllTokensForUser($user->id);

    return redirect()->route('users.index')
                     ->with('success', 'User berhasil dihapus.');
}
}
