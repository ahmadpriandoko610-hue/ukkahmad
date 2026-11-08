<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class RoomController extends Controller
{
    /**
     * Menampilkan semua ruangan
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $query = Room::with('pic')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('room_name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('pic', function ($picQuery) use ($search) {
                            $picQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            });

        $rawRooms = $query->get();
        $sortedRooms = Room::sortRooms($rawRooms);

        $perPage = 10;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $sortedRooms->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $rooms = new LengthAwarePaginator(
            $currentItems,
            $sortedRooms->count(),
            $perPage,
            $currentPage,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(),
            ]
        );

        return view('admin.rooms.index', compact('rooms', 'search'));
    }

    /**
     * Form tambah ruangan
     */
    public function create()
    {
        // Ambil semua user dengan role PIC
        $pics = User::where('role', 'pic')
            ->orderBy('name')
            ->get();

        return view('admin.rooms.create', compact('pics'));
    }

    /**
     * Menyimpan ruangan baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'room_name'   => 'required|string|max:255',
            'location'    => 'required|string|max:255',
            'capacity'    => 'required|integer|min:1',
            'pic_id'      => 'required|exists:users,id',
            'description' => 'nullable|string',
        ], [
            'room_name.required' => 'Nama ruangan wajib diisi.',
            'location.required'  => 'Lokasi ruangan wajib diisi.',
            'capacity.required'  => 'Kapasitas wajib diisi.',
            'capacity.integer'   => 'Kapasitas harus berupa angka.',
            'capacity.min'       => 'Kapasitas minimal 1.',
            'pic_id.required'    => 'PIC ruangan wajib dipilih.',
            'pic_id.exists'      => 'PIC yang dipilih tidak valid.',
        ]);

        // Pastikan user yang dipilih memang PIC
        $pic = User::where('id', $request->pic_id)
            ->where('role', 'pic')
            ->first();

        if (!$pic) {
            return back()
                ->withInput()
                ->withErrors([
                    'pic_id' => 'User yang dipilih bukan PIC.',
                ]);
        }

        Room::create([
            'room_name'   => $request->room_name,
            'location'    => $request->location,
            'capacity'    => $request->capacity,
            'pic_id'      => $request->pic_id,
            'description' => $request->description,
            'is_active'   => true,
        ]);

        return redirect()
            ->route('rooms.index')
            ->with('success', 'Ruangan berhasil ditambahkan.');
    }

    /**
     * Menampilkan detail ruangan
     */
    public function show(Room $room)
    {
        //
    }

    /**
     * Form edit ruangan
     */
    public function edit(Room $room)
    {
        // Ambil semua user dengan role PIC
        $pics = User::where('role', 'pic')
            ->orderBy('name')
            ->get();

        return view(
            'admin.rooms.edit',
            compact('room', 'pics')
        );
    }

    /**
     * Mengupdate ruangan
     */
    public function update(Request $request, Room $room)
    {
        $request->validate([
            'room_name'   => 'required|string|max:255',
            'location'    => 'required|string|max:255',
            'capacity'    => 'required|integer|min:1',
            'pic_id'      => 'required|exists:users,id',
            'description' => 'nullable|string',
        ], [
            'room_name.required' => 'Nama ruangan wajib diisi.',
            'location.required'  => 'Lokasi ruangan wajib diisi.',
            'capacity.required'  => 'Kapasitas wajib diisi.',
            'capacity.integer'   => 'Kapasitas harus berupa angka.',
            'capacity.min'       => 'Kapasitas minimal 1.',
            'pic_id.required'    => 'PIC ruangan wajib dipilih.',
            'pic_id.exists'      => 'PIC yang dipilih tidak valid.',
        ]);

        // Pastikan user yang dipilih memang PIC
        $pic = User::where('id', $request->pic_id)
            ->where('role', 'pic')
            ->first();

        if (!$pic) {
            return back()
                ->withInput()
                ->withErrors([
                    'pic_id' => 'User yang dipilih bukan PIC.',
                ]);
        }

        $room->update([
            'room_name'   => $request->room_name,
            'location'    => $request->location,
            'capacity'    => $request->capacity,
            'pic_id'      => $request->pic_id,
            'description' => $request->description,
        ]);

        return redirect()
            ->route('rooms.index')
            ->with('success', 'Ruangan berhasil diperbarui.');
    }

    /**
     * Menghapus ruangan
     */
    public function destroy(Room $room)
    {
        $room->delete();

        return redirect()
            ->route('rooms.index')
            ->with('success', 'Ruangan berhasil dihapus.');
    }
}
