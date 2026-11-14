@if ($row->follow_up_at)
    <bdi dir="ltr" @class(['text-danger fw-semibold' => $row->follow_up_at->isPast() && ! $row->isClosed()])>{{ fmt_datetime($row->follow_up_at) }}</bdi>
@endif
