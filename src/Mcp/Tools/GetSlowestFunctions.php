<?php

namespace Codemonkey\SPXMcpServer\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

class GetSlowestFunctions extends ProfileAnalysisTool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Get the slowest functions from an SPX profile by exclusive wall time.';

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

        $functions = $parser->getSlowestFunctions($limit);
        $output = $this->formatFunctionStats($functions, 'Slowest Functions by Exclusive Time', 'exclusive_time');

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