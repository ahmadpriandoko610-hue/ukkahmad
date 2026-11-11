<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Menampilkan daftar user.
     */
   public function index(Request $request)
{
    $keyword = $request->keyword;

    $users = User::when($keyword, function ($query) use ($keyword) {
        $query->where('name', 'like', "%{$keyword}%")
              ->orWhere('email', 'like', "%{$keyword}%");
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
        $isPic = $request->role === 'pic';

        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => 'required|in:admin,pic,guru,siswa',
            'phone' => $isPic ? 'nullable|string|max:20' : 'nullable',
        ]);

        $rawPhone = trim((string) $request->phone);
        $phone = ($isPic && $rawPhone !== '') ? $rawPhone : null;

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
        return view('admin.users.edit', compact('user'));
    }

    /**
     * Mengupdate user.
     */
    public function update(Request $request, User $user)
    {
        $isPic = $request->role === 'pic';

        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:admin,pic,guru,siswa',
            'phone' => $isPic ? 'nullable|string|max:20' : 'nullable',
        ]);

        $rawPhone = trim((string) $request->phone);
        $phone = ($isPic && $rawPhone !== '') ? $rawPhone : null;

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
     * Menghapus user.
     */
   public function destroy(User $user)
{
    $user->delete();

    return redirect()->route('users.index')
                     ->with('success', 'User berhasil dihapus.');
}
}
