<?php

namespace App\Http\Controllers\Api;

use App\Enums\BotSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\BotSession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BotAnalyticsController extends Controller
{
    public function getAnalytics(Request $request, Bot $bot)
    {
        $input = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = Carbon::parse($input['start_date'])->startOfDay();
        $endDate = Carbon::parse($input['end_date'])->endOfDay();

        $overview = $this->getOverviewMetrics($bot, $startDate, $endDate);

        $timeSeries = $this->getTimeSeriesData($bot, $startDate, $endDate);

        return response()->json([
            'data' => [
                'overview' => $overview,
                'time_series' => $timeSeries,
            ],
        ]);
    }

    /**
     * Get overview metrics for the boxes
     */
    private function getOverviewMetrics(Bot $bot, Carbon $startDate, Carbon $endDate)
    {
        // Current active sessions (right now, not in date range)
        $activeSessions = BotSession::where('bot_id', $bot->id)
            ->whereIn('status', [BotSessionStatus::ACTIVE, BotSessionStatus::WAITING])
            ->count();

        // Sessions within date range
        $sessionStats = BotSession::where('bot_id', $bot->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("
                COUNT(*) as total_sessions,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_sessions,
                COUNT(CASE WHEN status = 'abandoned' THEN 1 END) as abandoned_sessions,
                AVG(CASE 
                    WHEN status IN ('completed', 'abandoned') 
                    THEN EXTRACT(EPOCH FROM (updated_at - created_at)) 
                    ELSE NULL 
                END) as avg_duration_seconds
            ")
            ->first();

        return [
            'active_sessions' => $activeSessions,
            'total_sessions' => (int) $sessionStats->total_sessions,
            'completed_sessions' => (int) $sessionStats->completed_sessions,
            'abandoned_sessions' => (int) $sessionStats->abandoned_sessions,
            'avg_duration_seconds' => round($sessionStats->avg_duration_seconds ?? 0),
        ];
    }

    /**
     * Get time series data for charts
     */
    private function getTimeSeriesData(Bot $bot, Carbon $startDate, Carbon $endDate)
    {
        $dateTrunc = "DATE_TRUNC('day', created_at)";

        $dateTruncUpdated = "DATE_TRUNC('day', updated_at)";

        $sessionsOpened = DB::table('bot_sessions')
            ->where('bot_id', $bot->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("
                {$dateTrunc} as period,
                COUNT(*) as opened_sessions,
                COUNT(DISTINCT contact_id) as unique_users
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $sessionsClosed = DB::table('bot_sessions')
            ->where('bot_id', $bot->id)
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->whereIn('status', ['completed', 'abandoned'])
            ->selectRaw("
                {$dateTruncUpdated} as period,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_sessions,
                COUNT(CASE WHEN status = 'abandoned' THEN 1 END) as abandoned_sessions,
                AVG(EXTRACT(EPOCH FROM (updated_at - created_at))) as avg_duration_seconds
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $periods = $this->generateDateRange($startDate, $endDate);

        $mergedData = collect($periods)->map(function ($period) use ($sessionsOpened, $sessionsClosed) {
            $opened = $sessionsOpened->firstWhere('period', $period);
            $closed = $sessionsClosed->firstWhere('period', $period);

            return [
                'period' => $period,
                'opened_sessions' => $opened->opened_sessions ?? 0,
                'unique_users' => $opened->unique_users ?? 0,
                'completed_sessions' => $closed->completed_sessions ?? 0,
                'abandoned_sessions' => $closed->abandoned_sessions ?? 0,
                'avg_duration_seconds' => round($closed->avg_duration_seconds ?? 0),
            ];
        });

        return $mergedData;
    }

    /**
     * Generate complete date range for daily granularity
     */
    private function generateDateRange(Carbon $startDate, Carbon $endDate): array
    {
        $periods = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $periods[] = $current->format('Y-m-d');
            $current = $current->addDay();
        }

        return array_unique($periods);
    }

    /**
     * Format duration in seconds to human readable format
     */
    private function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds).'s';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;

            return $minutes.'m '.round($remainingSeconds).'s';
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);

            return $hours.'h '.$minutes.'m';
        }
    }

    /**
     * Get bot flow analytics
     */
    public function getFlowAnalytics(Request $request, Bot $bot)
    {
        $input = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'flow_id' => ['sometimes', 'exists:bot_flows,id'],
        ]);

        $startDate = Carbon::parse($input['start_date'])->startOfDay();
        $endDate = Carbon::parse($input['end_date'])->endOfDay();
        $flowId = $input['flow_id'] ?? $bot->activeFlow?->id;

        if (! $flowId) {
            return response()->json([
                'message' => 'No active flow found for this bot',
            ], 404);
        }

        // Get flow funnel data
        $funnel = $this->getFlowFunnel($flowId, $startDate, $endDate);

        // Get node performance
        $nodePerformance = $this->getNodePerformance($flowId, $startDate, $endDate);

        return response()->json([
            'flow_id' => $flowId,
            'period' => [
                'start' => $startDate->toDateTimeString(),
                'end' => $endDate->toDateTimeString(),
            ],
            'funnel' => $funnel,
            'node_performance' => $nodePerformance,
        ]);
    }

    /**
     * Get flow funnel data
     */
    private function getFlowFunnel(string $flowId, Carbon $startDate, Carbon $endDate)
    {
        // This would require tracking session progress through nodes
        // For now, returning a simplified version
        $sessions = BotSession::where('bot_flow_id', $flowId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("
                COUNT(*) as total_started,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as reached_end,
                COUNT(CASE WHEN status = 'abandoned' AND current_node_id IS NOT NULL THEN 1 END) as dropped_off
            ")
            ->first();

        return [
            'total_started' => (int) $sessions->total_started,
            'reached_end' => (int) $sessions->reached_end,
            'dropped_off' => (int) $sessions->dropped_off,
            'completion_rate' => $sessions->total_started > 0
                ? round(($sessions->reached_end / $sessions->total_started) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get node performance metrics
     */
    private function getNodePerformance(string $flowId, Carbon $startDate, Carbon $endDate)
    {
        return DB::table('bot_sessions')
            ->where('bot_flow_id', $flowId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('current_node_id')
            ->selectRaw("
                current_node_id as node_id,
                COUNT(*) as visits,
                COUNT(CASE WHEN status = 'abandoned' THEN 1 END) as drop_offs,
                AVG(EXTRACT(EPOCH FROM (updated_at - created_at))) as avg_time_spent
            ")
            ->groupBy('current_node_id')
            ->orderByDesc('drop_offs')
            ->limit(10)
            ->get()
            ->map(function ($node) {
                $node->drop_off_rate = $node->visits > 0
                    ? round(($node->drop_offs / $node->visits) * 100, 1)
                    : 0;
                $node->avg_time_spent_formatted = $this->formatDuration($node->avg_time_spent ?? 0);

                return $node;
            });
    }
}
