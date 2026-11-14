<form method="POST" action="{{ route('notifications.read', $row->id) }}" class="d-inline">
    @csrf
    <button type="submit" @class(['btn-link-plain', 'fw-semibold' => $row->read_at === null])>{{ \App\Notifications\SystemNotification::title($row->data) }}</button>
</form>
