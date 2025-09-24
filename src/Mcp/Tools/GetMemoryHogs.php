<?php

namespace Codemonkey\SPXMcpServer\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class GetMemoryHogs extends ProfileAnalysisTool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Get functions with highest memory usage from an SPX profile.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $profileKey = $request->get('profile_key');
        $limit = $request->get('limit', 50);

        $parser = $this->parseProfile($profileKey);
        if ($parser instanceof Response) {
            return $parser;
        }

        $functions = $parser->getMemoryHogs($limit);
        $output = $this->formatFunctionStats($functions, 'Top Memory Users', 'inclusive_memory');

        return Response::text($output);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->getBaseSchema($schema);
    }
}