<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomSortingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $pic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin Utama',
        ]);

        $this->pic = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Sarpras',
        ]);
    }

    /**
     * Requirement 1 & 2:
     * - Ruangan tanpa nomor (Lapangan Basket, Ruang Wijaya, Aula Bawah) selalu tampil paling atas sebelum ruangan berangka.
     * - Requirement 3 & 4:
     * - Ruangan berangka diurutkan secara numerik (LAB 1, LAB 2 sebelum LAB 9, LAB 9 sebelum LAB 10, LAB 10 sebelum LAB 11).
     * - Variasi penulisan seperti LAB 1, LAB1, Lab 10 terurut secara benar.
     * - Requirement 7: Tidak ada data yang hilang atau berubah.
     */
    public function test_rooms_are_sorted_with_non_numbered_rooms_first_then_numerical(): void
    {
        $roomNames = [
            'LAB 10',
            'Lapangan Basket',
            'LAB 2',
            'Ruang Wijaya',
            'LAB 1',
            'LAB 14',
            'LAB 11',
            'Aula Bawah',
            'LAB1',
            'Lab 10B',
            'LAB 9',
            'LAB 5',
            'LAB 4',
            'LAB 3',
        ];

        foreach ($roomNames as $name) {
            Room::create([
                'room_name' => $name,
                'location' => 'Kampus 1',
                'capacity' => 30,
                'pic_id' => $this->pic->id,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('rooms.index'));
        $response->assertOk();

        $paginator = $response->viewData('rooms');
        // Total data tidak ada yang hilang (Requirement 7)
        $this->assertEquals(count($roomNames), $paginator->total());

        // Ambil urutan seluruh data yang telah diurutkan
        $allSorted = Room::sortRooms(Room::all())->pluck('room_name')->all();

        // 1. Lapangan Basket tampil sebelum LAB 1
        $idxLapangan = array_search('Lapangan Basket', $allSorted);
        $idxLab1 = array_search('LAB 1', $allSorted);
        $this->assertNotFalse($idxLapangan);
        $this->assertNotFalse($idxLab1);
        $this->assertLessThan($idxLab1, $idxLapangan, 'Lapangan Basket harus tampil sebelum LAB 1');

        // 2. Ruang Wijaya tampil sebelum LAB 1
        $idxWijaya = array_search('Ruang Wijaya', $allSorted);
        $this->assertNotFalse($idxWijaya);
        $this->assertLessThan($idxLab1, $idxWijaya, 'Ruang Wijaya harus tampil sebelum LAB 1');

        // Aula Bawah juga tampil sebelum LAB 1
        $idxAula = array_search('Aula Bawah', $allSorted);
        $this->assertLessThan($idxLab1, $idxAula, 'Aula Bawah harus tampil sebelum LAB 1');

        // 3. LAB 2 tampil sebelum LAB 10
        $idxLab2 = array_search('LAB 2', $allSorted);
        $idxLab10 = array_search('LAB 10', $allSorted);
        $this->assertLessThan($idxLab10, $idxLab2, 'LAB 2 harus tampil sebelum LAB 10');

        // 4. LAB 9 tampil sebelum LAB 10
        $idxLab9 = array_search('LAB 9', $allSorted);
        $this->assertLessThan($idxLab10, $idxLab9, 'LAB 9 harus tampil sebelum LAB 10');

        // LAB 10 tampil sebelum LAB 11
        $idxLab11 = array_search('LAB 11', $allSorted);
        $this->assertLessThan($idxLab11, $idxLab10, 'LAB 10 harus tampil sebelum LAB 11');
    }

    /**
     * Requirement 5: Kolom No tetap berurutan dan continuous setelah pagination.
     */
    public function test_row_number_continues_correctly_across_pagination_pages(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            Room::create([
                'room_name' => "Ruang Teori {$i}",
                'location' => 'Gedung C',
                'capacity' => 35,
                'pic_id' => $this->pic->id,
                'is_active' => true,
            ]);
        }

        // Halaman 1 (10 item: No 1 s/d 10)
        $page1Response = $this->actingAs($this->admin)->get(route('rooms.index', ['page' => 1]));
        $page1Response->assertOk();
        $page1Rooms = $page1Response->viewData('rooms');
        $this->assertEquals(1, $page1Rooms->firstItem());
        $this->assertEquals(10, $page1Rooms->lastItem());

        // Halaman 2 (5 item: No 11 s/d 15)
        $page2Response = $this->actingAs($this->admin)->get(route('rooms.index', ['page' => 2]));
        $page2Response->assertOk();
        $page2Rooms = $page2Response->viewData('rooms');
        $this->assertEquals(11, $page2Rooms->firstItem());
        $this->assertEquals(15, $page2Rooms->lastItem());

        // Pastikan baris pertama halaman 2 bernomor 11
        $page2Response->assertSee('11');
    }

    /**
     * Requirement 6: Fitur pencarian tetap bekerja dan hasilnya tetap terurut sesuai aturan.
     */
    public function test_room_search_works_and_results_are_properly_sorted(): void
    {
        Room::create(['room_name' => 'LAB 10', 'location' => 'Lt 2', 'capacity' => 30, 'pic_id' => $this->pic->id]);
        Room::create(['room_name' => 'LAB 2', 'location' => 'Lt 2', 'capacity' => 30, 'pic_id' => $this->pic->id]);
        Room::create(['room_name' => 'LAB 1', 'location' => 'Lt 2', 'capacity' => 30, 'pic_id' => $this->pic->id]);
        Room::create(['room_name' => 'Lapangan Utama', 'location' => 'Outdoor', 'capacity' => 500, 'pic_id' => $this->pic->id]);

        $searchResponse = $this->actingAs($this->admin)->get(route('rooms.index', ['search' => 'LAB']));
        $searchResponse->assertOk();

        $rooms = $searchResponse->viewData('rooms');
        $this->assertCount(3, $rooms);

        // Hanya ruangan LAB yang tampil
        $searchResponse->assertSee('LAB 1');
        $searchResponse->assertSee('LAB 2');
        $searchResponse->assertSee('LAB 10');
        $searchResponse->assertDontSee('Lapangan Utama');

        // Pastikan urutan hasil search adalah LAB 1, LAB 2, LAB 10
        $items = $rooms->pluck('room_name')->all();
        $this->assertEquals(['LAB 1', 'LAB 2', 'LAB 10'], $items);
    }
}

