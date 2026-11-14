<bdi dir="ltr" @class(['text-danger' => $row->isOverdue()])>{{ fmt_date($row->due_date) }}</bdi>
