<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarRoleColorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $guru;
    private User $pic;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Administrator Sekolah',
        ]);

        $this->guru = User::factory()->create([
            'role' => 'guru',
            'name' => 'Bapak Guru Pembina',
        ]);

        $this->pic = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Sarana',
        ]);

        $this->room = Room::create([
            'room_name' => 'Aula Serbaguna',
            'location' => 'Lantai 1',
            'capacity' => 200,
            'pic_id' => $this->pic->id,
            'is_active' => true,
        ]);
    }

    public function test_calendar_payload_includes_creator_role_and_label(): void
    {
        $adminEvent = Event::create([
            'event_name' => 'Ujian Akhir Semester (Hari Penting)',
            'date' => now()->addDays(2)->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'requester_id' => $this->admin->id,
            'room_id' => $this->room->id,
            'status' => 'Disetujui',
        ]);

        $guruEvent = Event::create([
            'event_name' => 'Latihan Rutin Pramuka Organisasi',
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '13:00:00',
            'end_time' => '15:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room->id,
            'status' => 'Disetujui',
        ]);

        $this->assertEquals('admin', $adminEvent->creator_role);
        $this->assertEquals('Admin / Sekolah', $adminEvent->creator_label);

        $this->assertEquals('guru', $guruEvent->creator_role);
        $this->assertEquals('Guru / Organisasi', $guruEvent->creator_label);

        // Teacher Calendar
        $responseTeacher = $this->actingAs($this->guru)->get(route('guru.calendar'));
        $responseTeacher->assertOk();
        $responseTeacher->assertSee('Hari Penting / Agenda Admin (Sekolah)');
        $responseTeacher->assertSee('Agenda Guru / Organisasi');
        $eventsTeacher = $responseTeacher->viewData('events');
        $this->assertCount(2, $eventsTeacher);
        $this->assertEquals('admin', $eventsTeacher[0]['creator_role']);
        $this->assertEquals('guru', $eventsTeacher[1]['creator_role']);

        // Admin Calendar
        $responseAdmin = $this->actingAs($this->admin)->get(route('admin.calendar'));
        $responseAdmin->assertOk();
        $responseAdmin->assertSee('Hari Penting / Agenda Admin (Sekolah)');

        // PIC Calendar
        $responsePic = $this->actingAs($this->pic)->get(route('pic.calendar'));
        $responsePic->assertOk();
        $responsePic->assertSee('Hari Penting / Agenda Admin (Sekolah)');
        $eventsPic = $responsePic->viewData('events');
        $this->assertCount(2, $eventsPic);
        $this->assertEquals('admin', $eventsPic[0]['creator_role']);
        $this->assertEquals('guru', $eventsPic[1]['creator_role']);

        // Public Calendar
        $responsePublic = $this->get(route('calendar.public'));
        $responsePublic->assertOk();
        $responsePublic->assertSee('Hari Penting / Agenda Admin (Sekolah)');
        $eventsPublic = $responsePublic->viewData('events');
        $this->assertCount(2, $eventsPublic);
        $this->assertEquals('admin', $eventsPublic[0]['creator_role']);
        $this->assertEquals('guru', $eventsPublic[1]['creator_role']);
    }

    public function test_national_calendar_service_provides_holidays_and_important_days(): void
    {
        $sumpahPemuda = \App\Services\NationalCalendarService::getForDate('2026-10-28');
        $this->assertNotNull($sumpahPemuda);
        $this->assertEquals('Hari Sumpah Pemuda', $sumpahPemuda['name']);
        $this->assertFalse($sumpahPemuda['is_holiday']);

        $idulFitri = \App\Services\NationalCalendarService::getForDate('2026-03-20');
        $this->assertNotNull($idulFitri);
        $this->assertStringContainsString('Idul Fitri', $idulFitri['name']);
        $this->assertTrue($idulFitri['is_holiday']);

        $responseTeacher = $this->actingAs($this->guru)->get(route('guru.calendar'));
        $responseTeacher->assertOk();
        $responseTeacher->assertSee('Hari Libur Nasional');
        $responseTeacher->assertSee('Hari Penting Nasional');
        $this->assertNotEmpty($responseTeacher->viewData('nationalHolidays'));

        $responsePublic = $this->get(route('calendar.public'));
        $responsePublic->assertOk();
        $responsePublic->assertSee('Hari Libur Nasional');
        $responsePublic->assertSee('function escapeHtml(value)', false);
        $this->assertNotEmpty($responsePublic->viewData('nationalHolidays'));
    }
}

