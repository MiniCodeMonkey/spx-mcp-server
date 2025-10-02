<?php

namespace Codemonkey\SPXMcpServer\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class GetCallTree extends ProfileAnalysisTool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Get the hierarchical call tree structure showing parent-child relationships between functions.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $profileKey = $request->get('profile_key');

        $parser = $this->parseProfile($profileKey);
        if ($parser instanceof Response) {
            return $parser;
        }

        $callTree = $parser->getCallTree();

        $output = "=== Function Call Tree ===\n\n";
        $output .= sprintf("Total unique functions: %d\n\n", count($callTree));

        foreach ($callTree as $funcIdx => $node) {
            $output .= sprintf("%s\n", $node['name']);
            if (!empty($node['children'])) {
                $output .= sprintf("  Children: %d functions\n", count($node['children']));
                foreach (array_slice(array_keys($node['children']), 0, 5) as $childIdx) {
                    $childName = $callTree[$childIdx]['name'] ?? 'Unknown';
                    $output .= sprintf("    - %s\n", substr($childName, 0, 60));
                }
                if (count($node['children']) > 5) {
                    $output .= sprintf("    ... and %d more\n", count($node['children']) - 5);
                }
            }
            $output .= "\n";
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
        ];
    }
}