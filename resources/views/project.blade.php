@extends('warden::layout')
@php use VictorStochero\Warden\Dashboard\Format; @endphp

@section('title', $project->name)
@section('heading', $project->name)
@section('subheading', ucfirst($section) . ' · last seen ' . Format::ago($project->last_seen_at))

@section('content')
    @php Format::tz($project->timezone ?? null); $k = $kpis; @endphp

    {{-- KPI strip --}}
    @php
        $reqUrl = route('warden.project.section', ['project' => $project->slug, 'section' => 'requests', 'range' => $range]);
        $errorsUrl = route('warden.project.section', ['project' => $project->slug, 'section' => 'errors', 'range' => $range]);
        $jobsUrl = route('warden.project.section', ['project' => $project->slug, 'section' => 'jobs', 'range' => $range]);
        $cacheUrl = route('warden.project.section', ['project' => $project->slug, 'section' => 'cache', 'range' => $range]);
        $issuesUrl = route('warden.issues', $project->slug);
        $incidentsUrl = route('warden.incidents', $project->slug);
        $uptimeUrl = route('warden.project.section', ['project' => $project->slug, 'section' => 'uptime', 'range' => $range]);
    @endphp
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 xl:grid-cols-8">
        @include('warden::partials.kpi', ['label' => 'Throughput', 'value' => Format::num($k['throughput']), 'sub' => 'requests', 'tone' => 'brand', 'link' => $reqUrl])
        @include('warden::partials.kpi', ['label' => 'Error rate', 'value' => $k['error_rate'].'%', 'tone' => $k['error_rate'] >= 5 ? 'rose' : ($k['error_rate'] >= 1 ? 'amber' : 'emerald'), 'sub' => Format::num($k['errors']).' errors', 'link' => $errorsUrl])
        @include('warden::partials.kpi', ['label' => 'p95 latency', 'value' => $k['p95'] !== null ? Format::ms($k['p95']) : '—', 'link' => $reqUrl])
        @include('warden::partials.kpi', ['label' => 'Slow reqs', 'value' => Format::num($k['slow']), 'tone' => $k['slow'] ? 'amber' : 'slate', 'link' => $reqUrl])
        @include('warden::partials.kpi', ['label' => 'Failed jobs', 'value' => Format::num($k['failed_jobs']), 'tone' => $k['failed_jobs'] ? 'rose' : 'slate', 'link' => $jobsUrl])
        @include('warden::partials.kpi', ['label' => 'Cache hit', 'value' => $k['cache_hit_rate'] !== null ? $k['cache_hit_rate'].'%' : '—', 'tone' => 'sky', 'link' => $cacheUrl])
        @include('warden::partials.kpi', ['label' => 'Open issues', 'value' => Format::num($k['open_issues']), 'tone' => $k['open_issues'] ? 'rose' : 'emerald', 'link' => $issuesUrl])
        @include('warden::partials.kpi', ['label' => 'Uptime · 30d', 'value' => $k['uptime'] . '%', 'tone' => $k['uptime'] >= 99.5 ? 'emerald' : ($k['uptime'] >= 95 ? 'amber' : 'rose'), 'link' => $uptimeUrl])
    </div>

    <div class="mt-7">
        @include('warden::partials.sections.' . (in_array($section, ['requests','errors','queries','jobs','cache','schedule','http','logs','mail','host','security','delivery','uptime']) ? $section : 'overview'))
    </div>
@endsection
