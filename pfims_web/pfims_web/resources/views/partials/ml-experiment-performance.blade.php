<section id="experimentPerformancePanel" class="experiment-performance" aria-labelledby="experimentPerformanceTitle">
    <div class="experiment-performance-heading"><div><h3 id="experimentPerformanceTitle">Overrun Classification Performance</h3><p id="experimentTestCount">Loading test count…</p></div></div>
    <div class="experiment-metric-grid">
        <div><span>Accuracy</span><strong id="experimentAccuracy">—</strong></div>
        <div><span>Precision</span><strong id="experimentPrecision">—</strong></div>
        <div><span>Recall</span><strong id="experimentRecall">—</strong></div>
        <div><span>F1 score</span><strong id="experimentF1">—</strong></div>
        <div><span>Balanced accuracy</span><strong id="experimentBalancedAccuracy">—</strong></div>
    </div>
    <p id="experimentPerformanceCounts" class="experiment-count-grid" aria-live="polite"></p>
</section>
<script type="application/json" id="experimentPerformanceBootstrap">@json($paceExperiment ?? null)</script>
<script type="application/json" id="mergedClassificationBootstrap">@json($mergedPerformance ?? null)</script>
<link rel="stylesheet" href="{{ asset('css/ml-experiment-performance.css') }}?v={{ filemtime(public_path('css/ml-experiment-performance.css')) }}">
<script src="{{ asset('js/ml-experiment-performance.js') }}?v={{ filemtime(public_path('js/ml-experiment-performance.js')) }}"></script>
