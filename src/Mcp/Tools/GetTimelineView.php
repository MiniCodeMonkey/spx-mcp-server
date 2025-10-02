<?php

namespace Codemonkey\SPXMcpServer\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class GetTimelineView extends ProfileAnalysisTool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Get a timeline view of function execution showing the order and timing of function calls. Also provides execution phases grouped by namespace patterns.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $profileKey = $request->get('profile_key');
        $viewType = $request->get('view_type', 'phases');
        $limit = $request->get('limit', 100);

        $parser = $this->parseProfile($profileKey);
        if ($parser instanceof Response) {
            return $parser;
        }

        if ($viewType === 'timeline') {
            $timeline = $parser->getTimelineView();
            $output = sprintf("=== Execution Timeline (Showing %d of %d events) ===\n\n",
                min($limit, count($timeline)), count($timeline));

            foreach (array_slice($timeline, 0, $limit) as $entry) {
                $indent = str_repeat('  ', $entry['level']);
                $type = $entry['is_start'] ? '→' : '←';
                $output .= sprintf("%s%s %s @ %s\n",
                    $indent,
                    $type,
                    substr($entry['func_name'], 0, 50),
                    $this->formatTime($entry['timestamp'])
                );
            }
        } else {
            $phases = $parser->getExecutionPhases();
            $output = "=== Execution Phases ===\n\n";

            foreach ($phases as $phaseName => $phaseEvents) {
                if (empty($phaseEvents)) continue;

                $output .= sprintf("%s Phase:\n", ucfirst($phaseName));
                $output .= sprintf("  Functions: %d\n", count($phaseEvents));
                $output .= "  Sample functions:\n";

                foreach (array_slice($phaseEvents, 0, 3) as $event) {
                    $output .= sprintf("    - %s\n", substr($event['func_name'], 0, 50));
                }
                $output .= "\n";
            }
        }

        return Response::text($output);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'profile_key' => $schema->string()
                ->description('The SPX profile key to analyze.')
                ->required(),
            'view_type' => $schema->string()
                ->description('Type of view: "timeline" for sequential execution or "phases" for grouped phases')
                ->enum(['timeline', 'phases'])
                ->default('phases'),
            'limit' => $schema->integer()
                ->description('For timeline view, limit number of events (default: 100)')
                ->default(100),
        ];
    }
}