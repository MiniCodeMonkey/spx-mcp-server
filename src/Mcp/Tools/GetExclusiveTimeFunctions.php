<?php

namespace Codemonkey\SPXMcpServer\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class GetExclusiveTimeFunctions extends ProfileAnalysisTool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Get functions sorted by exclusive time (wall time minus children time). This shows which functions are actually spending time executing code vs waiting for child calls.';

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

        $functions = $parser->getExclusiveTimeFunctions($limit);
        $output = $this->formatFunctionStats($functions, 'Functions by Exclusive Time', 'exclusive_time');

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