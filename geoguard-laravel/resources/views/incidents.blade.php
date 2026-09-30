@extends('layouts.app')

@section('title', 'Incident Logs')

@section('content')
    <div class="max-w-7xl mx-auto p-8">
        <div class="flex justify-between items-center mb-8 border-b border-slate-800 pb-4">
            <h1 class="text-3xl font-black text-white">INCIDENT LOGS</h1>
            <div class="space-x-4">
                <a href="{{ route('incidents.export') }}" class="px-4 py-2 bg-cyan-700 hover:bg-cyan-600 text-white font-bold rounded transition shadow-[0_0_15px_rgba(6,182,212,0.3)]">Export CSV</a>
            </div>
        </div>

        <div class="bg-slate-900 rounded-lg border border-slate-800 overflow-hidden shadow-xl">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-950/50 text-slate-400 text-sm uppercase tracking-wider">
                        <th class="p-4 border-b border-slate-800">Date/Time</th>
                        <th class="p-4 border-b border-slate-800">Node</th>
                        <th class="p-4 border-b border-slate-800">Severity</th>
                        <th class="p-4 border-b border-slate-800">Max Tilt</th>
                        <th class="p-4 border-b border-slate-800">Snapshot</th>
                        <th class="p-4 border-b border-slate-800">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($incidents as $incident)
                        <tr class="hover:bg-slate-800/50 transition">
                            <td class="p-4 font-mono text-sm">{{ $incident->triggered_at->format('Y-m-d H:i:s') }}</td>
                            <td class="p-4 font-bold text-slate-200">{{ $incident->node->node_code ?? 'UNKNOWN' }}</td>
                            <td class="p-4">
                                @if($incident->severity === 'CRITICAL')
                                    <span class="px-2 py-1 bg-red-900/50 text-red-400 text-xs rounded border border-red-500/30 font-bold tracking-wide">CRITICAL</span>
                                @else
                                    <span class="px-2 py-1 bg-amber-900/50 text-amber-400 text-xs rounded border border-amber-500/30 font-bold tracking-wide">WARNING</span>
                                @endif
                            </td>
                            <td class="p-4 font-mono">{{ number_format($incident->max_tilt_angle, 1) }}&deg;</td>
                            <td class="p-4">
                                @if($incident->snapshot_path)
                                    <a href="{{ asset('storage/' . $incident->snapshot_path) }}" target="_blank">
                                        <img src="{{ asset('storage/' . $incident->snapshot_path) }}" alt="Snapshot" class="h-16 w-auto rounded border border-slate-700 hover:border-cyan-500 transition">
                                    </a>
                                @else
                                    <span class="text-slate-600 text-xs italic">No Image</span>
                                @endif
                            </td>
                            <td class="p-4">
                                <span class="text-slate-400 text-sm">{{ $incident->status }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500">No incident logs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
