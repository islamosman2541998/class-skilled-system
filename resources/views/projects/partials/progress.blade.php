<div class="progress-line" role="progressbar" aria-valuenow="{{ (float) $row->progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('projects.progress') }}">
    <span style="inline-size: {{ min(100, (float) $row->progress) }}%"></span>
</div>
<bdi dir="ltr" class="small">{{ rtrim(rtrim($row->progress, '0'), '.') ?: '0' }}%</bdi>
