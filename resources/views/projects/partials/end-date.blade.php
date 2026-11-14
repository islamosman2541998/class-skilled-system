@if ($row->planned_end_date)
    <bdi dir="ltr" @class(['text-danger fw-semibold' => $row->isLate()])>{{ fmt_date($row->planned_end_date) }}</bdi>
@endif
