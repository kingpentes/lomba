@extends('layouts.app')

@section('title', 'Dispatcher Panel')

@section('content')
<div class="max-w-7xl mx-auto p-8">
    <div class="flex justify-between items-center mb-6 border-b border-slate-800 pb-4">
        <div>
            <h1 class="text-3xl font-black text-white">DISPATCHER PANEL</h1>
            <p class="text-slate-400">Node Configuration & Command Control</p>
        </div>
        <div class="text-right">
            <span class="text-xs text-slate-500 font-mono">TOTAL NODES: {{ $nodes->count() }}</span>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- NODE SELECTOR / FILTER BAR                   -->
    <!-- ============================================ -->
    <div class="bg-slate-900 border border-slate-800 rounded-lg p-4 mb-6 flex flex-wrap items-center gap-4">
        <!-- Dropdown Select Node -->
        <div class="flex items-center gap-2">
            <label class="text-xs font-bold text-slate-500 tracking-wider whitespace-nowrap">SELECT NODE</label>
            <select id="nodeSelector" onchange="filterNodes()" class="bg-slate-950 border border-slate-700 text-slate-300 rounded px-3 py-2 text-sm focus:ring-cyan-500 focus:border-cyan-500 transition-colors min-w-[220px]">
                <option value="ALL">📡 All Nodes</option>
                @foreach($nodes as $node)
                <option value="{{ $node->node_code }}">{{ $node->node_code }} — {{ $node->name }}</option>
                @endforeach
            </select>
        </div>

        <!-- Status Filter Buttons -->
        <div class="flex items-center gap-2 ml-auto">
            <span class="text-xs font-bold text-slate-500 tracking-wider mr-1">FILTER</span>
            <button onclick="filterByStatus('ALL')" data-filter="ALL" class="filter-btn active-filter px-3 py-1.5 text-xs font-bold rounded border transition-colors bg-slate-800 text-slate-300 border-slate-600 hover:bg-slate-700">ALL</button>
            <button onclick="filterByStatus('CRITICAL')" data-filter="CRITICAL" class="filter-btn px-3 py-1.5 text-xs font-bold rounded border transition-colors bg-slate-800 text-slate-300 border-slate-600 hover:bg-red-900/50 hover:text-red-400 hover:border-red-500/30">🔴 CRITICAL</button>
            <button onclick="filterByStatus('WARNING')" data-filter="WARNING" class="filter-btn px-3 py-1.5 text-xs font-bold rounded border transition-colors bg-slate-800 text-slate-300 border-slate-600 hover:bg-amber-900/50 hover:text-amber-400 hover:border-amber-500/30">🟡 WARNING</button>
            <button onclick="filterByStatus('STABLE')" data-filter="STABLE" class="filter-btn px-3 py-1.5 text-xs font-bold rounded border transition-colors bg-slate-800 text-slate-300 border-slate-600 hover:bg-green-900/50 hover:text-green-400 hover:border-green-500/30">🟢 STABLE</button>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- NODE CARDS GRID                              -->
    <!-- ============================================ -->
    <div id="nodeGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($nodes as $node)
        <div class="node-card bg-slate-900 border border-slate-800 rounded-lg p-6 flex flex-col shadow-xl relative overflow-hidden group transition-all duration-300"
             data-node-code="{{ $node->node_code }}"
             data-status="{{ $node->status }}">
            <!-- decorative bg -->
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-cyan-900/10 rounded-full blur-3xl group-hover:bg-cyan-900/20 transition-all"></div>
            
            <div class="flex justify-between items-start mb-4 relative z-10">
                <div>
                    <h2 class="text-xl font-bold text-slate-200">{{ $node->node_code }}</h2>
                    <p class="text-sm text-slate-500">{{ $node->name }}</p>
                    <span class="active-indicator hidden text-[10px] font-bold text-emerald-400 bg-emerald-900/30 px-2 py-0.5 rounded border border-emerald-500/30 mt-1 inline-block">📡 ACTIVE ON HUD</span>
                    <p class="text-xs text-slate-600 mt-1 font-mono">📍 {{ number_format($node->latitude, 5) }}, {{ number_format($node->longitude, 5) }}</p>
                </div>
                <div>
                    @if($node->status === 'CRITICAL')
                        <span class="px-3 py-1 bg-red-900/50 text-red-400 text-xs rounded border border-red-500/30 font-bold tracking-wide animate-pulse">CRITICAL</span>
                    @elseif($node->status === 'WARNING')
                        <span class="px-3 py-1 bg-amber-900/50 text-amber-400 text-xs rounded border border-amber-500/30 font-bold tracking-wide">WARNING</span>
                    @elseif($node->status === 'OFFLINE')
                        <span class="px-3 py-1 bg-slate-800 text-slate-500 text-xs rounded border border-slate-600/30 font-bold tracking-wide">OFFLINE</span>
                    @else
                        <span class="px-3 py-1 bg-green-900/50 text-green-400 text-xs rounded border border-green-500/30 font-bold tracking-wide">STABLE</span>
                    @endif
                </div>
            </div>

            <!-- Latest Info Row -->
            <div class="relative z-10 border-t border-slate-800 mt-2 pt-3 grid grid-cols-2 gap-3 mb-4">
                <div>
                    <span class="text-[10px] font-bold text-slate-600 tracking-wider">LAST TELEMETRY</span>
                    <p class="text-xs text-slate-400 font-mono mt-0.5">
                        @if($node->latestTelemetry)
                            {{ $node->latestTelemetry->created_at->diffForHumans() }}
                        @else
                            <span class="text-slate-600">— no data —</span>
                        @endif
                    </p>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-slate-600 tracking-wider">LAST INCIDENT</span>
                    <p class="text-xs text-slate-400 font-mono mt-0.5">
                        @if($node->latestIncident)
                            <span class="{{ $node->latestIncident->severity === 'CRITICAL' ? 'text-red-400' : 'text-amber-400' }}">
                                {{ $node->latestIncident->triggered_at->diffForHumans() }}
                            </span>
                        @else
                            <span class="text-slate-600">— none —</span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="hidden flex-grow space-y-4 relative z-10 border-t border-slate-800 pt-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-2 tracking-wider">TELEMETRY MODE</label>
                    <select id="mode_{{ $node->node_code }}" class="w-full bg-slate-950 border border-slate-700 text-slate-300 rounded p-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors">
                        <option value="2000">🚀 Real-time Demo (2 Detik)</option>
                        <option value="60000">🕒 Power Saving / Adaptive (1 Menit)</option>
                        <option value="3600000">🐢 Deep Sleep (1 Jam)</option>
                    </select>
                </div>
            </div>

            <div class="mt-6 relative z-10 space-y-2">
                <button onclick="setActiveNode('{{ $node->node_code }}')" id="active_btn_{{ $node->node_code }}" class="w-full bg-emerald-700 hover:bg-emerald-600 text-white font-bold py-2.5 px-4 rounded transition shadow-[0_0_15px_rgba(16,185,129,0.15)] border border-emerald-500/50 text-sm">
                    📡 SET AS ACTIVE NODE (HUD)
                </button>
                <button onclick="setMode('{{ $node->node_code }}')" id="btn_{{ $node->node_code }}" class="hidden w-full bg-cyan-700 hover:bg-cyan-600 text-white font-bold py-2.5 px-4 rounded transition shadow-[0_0_15px_rgba(6,182,212,0.2)] border border-cyan-500/50 text-sm">
                    ⚙️ APPLY TELEMETRY CONFIG
                </button>
            </div>
        </div>
        @endforeach

        @if($nodes->isEmpty())
        <div class="col-span-full p-8 text-center bg-slate-900 border border-slate-800 rounded-lg shadow-inner">
            <p class="text-slate-500">Belum ada node yang terdaftar di database.</p>
        </div>
        @endif
    </div>

    <!-- No results message -->
    <div id="noResults" class="hidden col-span-full p-8 text-center bg-slate-900 border border-slate-800 rounded-lg shadow-inner mt-6">
        <p class="text-slate-500">Tidak ada node yang sesuai dengan filter.</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let currentStatusFilter = 'ALL';

    // ============================================
    // SET ACTIVE NODE (saves to localStorage for HUD Dashboard)
    // ============================================
    function setActiveNode(nodeCode) {
        localStorage.setItem('geoguard_active_node', nodeCode);
        highlightActiveNode();

        // Visual feedback on the button
        const btn = document.getElementById('active_btn_' + nodeCode);
        const originalText = btn.innerText;
        btn.innerText = '✅ ACTIVE — Dashboard will show this node';
        btn.classList.remove('bg-emerald-700', 'hover:bg-emerald-600', 'border-emerald-500/50');
        btn.classList.add('bg-green-600', 'border-green-400/50');

        setTimeout(() => {
            btn.innerText = originalText;
            btn.classList.add('bg-emerald-700', 'hover:bg-emerald-600', 'border-emerald-500/50');
            btn.classList.remove('bg-green-600', 'border-green-400/50');
        }, 3000);
    }

    function highlightActiveNode() {
        const activeNode = localStorage.getItem('geoguard_active_node') || '';
        document.querySelectorAll('.node-card').forEach(card => {
            const code = card.dataset.nodeCode;
            const indicator = card.querySelector('.active-indicator');
            if (code === activeNode) {
                card.classList.add('ring-2', 'ring-emerald-500/50');
                if (indicator) indicator.classList.remove('hidden');
            } else {
                card.classList.remove('ring-2', 'ring-emerald-500/50');
                if (indicator) indicator.classList.add('hidden');
            }
        });
    }

    function filterNodes() {
        const selector = document.getElementById('nodeSelector');
        const selectedNode = selector.value;
        applyFilters(selectedNode, currentStatusFilter);
    }

    function filterByStatus(status) {
        currentStatusFilter = status;

        // Update active button style
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active-filter', 'ring-1', 'ring-cyan-500', 'bg-cyan-900/30', 'text-cyan-300', 'border-cyan-500/50');
            btn.classList.add('bg-slate-800', 'text-slate-300', 'border-slate-600');
        });
        const activeBtn = document.querySelector(`[data-filter="${status}"]`);
        if (activeBtn) {
            activeBtn.classList.remove('bg-slate-800', 'text-slate-300', 'border-slate-600');
            activeBtn.classList.add('active-filter', 'ring-1', 'ring-cyan-500', 'bg-cyan-900/30', 'text-cyan-300', 'border-cyan-500/50');
        }

        const selector = document.getElementById('nodeSelector');
        applyFilters(selector.value, status);
    }

    function applyFilters(nodeCode, status) {
        const cards = document.querySelectorAll('.node-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const matchNode = (nodeCode === 'ALL' || card.dataset.nodeCode === nodeCode);
            const matchStatus = (status === 'ALL' || card.dataset.status === status);

            if (matchNode && matchStatus) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        document.getElementById('noResults').classList.toggle('hidden', visibleCount > 0);
    }

    function setMode(nodeCode) {
        const select = document.getElementById('mode_' + nodeCode);
        const interval = select.value;
        const btn = document.getElementById('btn_' + nodeCode);
        
        const originalText = btn.innerText;
        btn.innerText = '⏳ APPLYING...';
        btn.disabled = true;

        fetch(`/api/v1/nodes/${nodeCode}/set-interval`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ interval: parseInt(interval) })
        })
        .then(res => {
            if(!res.ok) {
                return res.json().then(err => { throw new Error(err.message || 'Server Error')});
            }
            return res.json();
        })
        .then(data => {
            btn.innerText = '✅ SUCCESS';
            btn.classList.remove('bg-cyan-700', 'hover:bg-cyan-600', 'border-cyan-500/50');
            btn.classList.add('bg-green-700', 'hover:bg-green-600', 'border-green-500/50');
            
            setTimeout(() => {
                btn.innerText = originalText;
                btn.classList.add('bg-cyan-700', 'hover:bg-cyan-600', 'border-cyan-500/50');
                btn.classList.remove('bg-green-700', 'hover:bg-green-600', 'border-green-500/50');
                btn.disabled = false;
            }, 3000);
        })
        .catch(err => {
            console.error(err);
            btn.innerText = '❌ FAILED';
            btn.classList.remove('bg-cyan-700', 'hover:bg-cyan-600', 'border-cyan-500/50');
            btn.classList.add('bg-red-700', 'hover:bg-red-600', 'border-red-500/50');
            
            setTimeout(() => {
                btn.innerText = originalText;
                btn.classList.add('bg-cyan-700', 'hover:bg-cyan-600', 'border-cyan-500/50');
                btn.classList.remove('bg-red-700', 'hover:bg-red-600', 'border-red-500/50');
                btn.disabled = false;
            }, 3000);
        });
    }

    // Initialize
    filterByStatus('ALL');
    highlightActiveNode();
</script>
@endpush
