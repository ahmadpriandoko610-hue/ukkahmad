@if (!empty($roomMoveWhatsapp['available']))
    <div
        role="region"
        aria-label="Pemberitahuan WhatsApp Pemindahan Ruangan Guru"
        style="background: #ffffff; border: 1px solid #cbd5e1; border-left: 4px solid #25D366; border-radius: 10px; padding: 16px 18px; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);"
    >
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
            <div>
                <h3 style="margin: 0 0 4px 0; font-size: 16px; color: #0f172a;">
                    Pemberitahuan WhatsApp Pemindahan Ruangan
                </h3>
                <p style="margin: 0; font-size: 13px; color: #475569;">
                    Pemindahan ruangan telah disimpan. Sistem menyiapkan pesan otomatis dari data agenda dan Guru pemilik agenda.
                </p>
            </div>

            <form action="{{ route('events.move.whatsapp.dismiss') }}" method="POST" style="margin: 0;">
                @csrf
                <input type="hidden" name="event_id" value="{{ $roomMoveWhatsapp['event_id'] }}">
                <button type="submit" class="btn btn-back" style="padding: 5px 10px; font-size: 12px;">
                    Tutup
                </button>
            </form>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 8px 16px; margin-top: 12px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #1e293b;">
            <div><strong>Nama Agenda:</strong> {{ $roomMoveWhatsapp['event_name'] }}</div>
            <div><strong>Penerima:</strong> {{ $roomMoveWhatsapp['recipient_name'] }}</div>
            <div><strong>Ruangan Sebelumnya:</strong> {{ $roomMoveWhatsapp['old_room_name'] }}</div>
            <div><strong>Ruangan Baru:</strong> {{ $roomMoveWhatsapp['new_room_name'] }}</div>
            <div><strong>Tanggal:</strong> {{ $roomMoveWhatsapp['date_range'] }}</div>
            <div><strong>Waktu:</strong> {{ $roomMoveWhatsapp['time_range'] }}</div>
        </div>

        @if (!empty($roomMoveWhatsapp['can_open_whatsapp']))
            <div style="margin-top: 14px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <form action="{{ route('events.move.whatsapp', $roomMoveWhatsapp['event_id']) }}" method="POST" target="_blank" style="margin: 0;">
                    @csrf
                    <button type="submit" class="complaint-wa-btn">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.669-.699c.969.586 1.761.88 2.791.88 3.182 0 5.768-2.587 5.768-5.766.001-3.187-2.575-5.766-5.768-5.766zm9.969 5.766c0 5.514-4.486 10-10 10-1.823 0-3.539-.49-5.031-1.355l-5.969 1.562 1.594-5.823c-.928-1.536-1.455-3.327-1.455-5.245 0-5.514 4.486-10 10-10s10 4.486 10 10z"/>
                        </svg>
                        Kirim Pemberitahuan WhatsApp kepada Guru
                    </button>
                </form>
            </div>
        @else
            <div class="alert-warning" style="margin: 12px 0 0 0;">
                <strong>Pemberitahuan WhatsApp tidak tersedia:</strong>
                {{ $roomMoveWhatsapp['phone_missing_message'] }}
            </div>
        @endif
    </div>
@endif
