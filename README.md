# Experimental PHP SPX MCP Server 🚀

An MCP (Model Context Protocol) server for **Laravel applications** that brings the power of [PHP SPX profiler](https://github.com/NoiseByNorthwest/php-spx) to Claude Code. Analyze PHP application performance with AI assistance, getting intelligent insights into bottlenecks, memory usage, database queries, and more.

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue)

## 🎯 What is This?

The PHP SPX MCP Server is a bridge between the powerful PHP SPX profiler and Claude Code (or any MCP-compatible AI client). It enables you to:

- 🔍 **Analyze performance profiles** with natural language queries
- 📊 **Get AI-powered insights** into bottlenecks and optimization opportunities
- 🎯 **Identify N+1 queries**, memory leaks, and slow functions automatically
- 📈 **Visualize call trees**, execution timelines, and middleware chains
- 💡 **Receive actionable recommendations** for performance improvements

Instead of manually digging through profiler data, simply ask Claude: *"What's causing the slowest response times?"* or *"Are there any N+1 query issues?"*

## 🛠️ Prerequisites

### PHP SPX Extension

You must have the PHP SPX extension installed and configured. Please refer to the official installation instructions at:

**[https://github.com/NoiseByNorthwest/php-spx](https://github.com/NoiseByNorthwest/php-spx)**

**Important:** Make sure to configure the `spx.data_dir` setting in your `php.ini`. This is where SPX will store profile data, and the MCP server needs to read from this location.

Example configuration:
```ini
extension=spx.so
spx.data_dir="/tmp/spx"
```

## 📦 Installation

Install the SPX MCP Server via Composer in your Laravel project:

```bash
composer require codemonkey/spx-mcp-server --dev
```

## ⚙️ Configuration

### Configure with Claude Code

The easiest way to configure the MCP server with Claude Code is using the built-in installation command:

```bash
php artisan spx-mcp:install
```

This will automatically add the SPX MCP server to your Claude Code configuration.

### Manual Configuration

Alternatively, you can manually add the server to your Claude Code configuration file (`~/.config/claude-code/config.json`):

```json
{
  "mcpServers": {
    "spx-mcp": {
      "command": "php",
      "args": ["artisan", "spx-mcp:start"],
      "cwd": "/path/to/your/laravel/project"
    }
  }
}
```

### Environment Variables

You can customize the SPX MCP server behavior using environment variables in your `.env` file:

```env
# Enable/disable the SPX MCP server
SPX_MCP_ENABLED=true

# SPX data directory (should match your php.ini configuration)
SPX_DATA_DIR=/tmp/spx
```

You can also publish the configuration file for more control:

```bash
php artisan vendor:publish --tag=spx-mcp-config
```

## 🎮 Usage

### 1. Generate SPX Profiles

Generate performance profiles by triggering SPX during your application requests. Add the SPX trigger to your request URL:

```bash
# Using the default key "dev"
curl "http://your-app.test/your-endpoint?SPX_KEY=dev&SPX_UI_URI=/"
```

Or use the SPX web UI to control profiling: `http://your-app.test/?SPX_KEY=dev&SPX_UI_URI=/`

Profiles will be saved to your configured `spx.data_dir`.

### 2. Analyze with Claude Code

Once you have generated profiles, open Claude Code and start asking questions:

**Example queries:**

- *"List all available SPX profiles"*
- *"Analyze the latest profile and show me the slowest functions"*
- *"Are there any N+1 database query issues?"*
- *"What middleware is taking the most time?"*
- *"Show me the call tree for the most recent profile"*
- *"Which third-party packages are impacting performance?"*
- *"Find any recursive functions and their overhead"*
- *"What's the wall time vs CPU time distribution?"*

Claude will use the MCP tools to parse your SPX profiles and provide intelligent analysis and recommendations.

## 🧰 Available MCP Tools

The SPX MCP server provides the following analysis tools:

| Tool | Description |
|------|-------------|
| `list_profiles` | List all available SPX profiles |
| `get_slowest_functions` | Get functions with highest exclusive execution time |
| `get_most_called_functions` | Get most frequently called functions |
| `get_memory_hogs` | Get functions using the most memory |
| `get_cpu_intensive_functions` | Get CPU-intensive functions |
| `get_call_tree` | Get hierarchical call tree structure |
| `get_timeline_view` | Get chronological execution timeline |
| `get_recursive_functions` | Identify recursive function calls |
| `get_wall_time_distribution` | Analyze I/O vs CPU time distribution |
| `get_autoloading_overhead` | Analyze autoloading performance impact |
| `get_third_party_package_impact` | Analyze third-party package performance |
| `get_database_queries` | Analyze database operations and detect N+1 queries |
| `get_redis_operations` | Analyze Redis/cache operations |
| `get_io_operations` | Analyze file, network, and socket I/O |
| `get_middleware_analysis` | Analyze middleware execution order and timing |

## 📊 Example Analysis

```
You: "Analyze the latest profile for performance issues"

Claude: I've analyzed your latest profile. Here are the key findings:

🔴 Critical Issues:
1. N+1 Query Detected: User::with('posts') is executing 156 queries
   - Location: UserController::index
   - Impact: 2.3s of database time
   - Fix: Use eager loading with constraints

2. Slow Function: ImageProcessor::resize
   - Exclusive time: 1.8s
   - Called: 45 times
   - Recommendation: Consider async processing or caching

🟡 Optimization Opportunities:
1. Autoloading overhead: 340ms across 89 class loads
   - Consider using composer dump-autoload -o

2. Middleware: AuthMiddleware taking 180ms
   - Session queries could be cached

📈 Overall Stats:
- Total execution time: 4.2s
- Peak memory: 45MB
- Total function calls: 12,847
```

## 🏗️ How It Works

1. **Profile Generation**: PHP SPX profiles your application and stores detailed execution traces
2. **Profile Parsing**: The MCP server reads and parses SPX profile files (compressed `.txt.gz` format)
3. **Data Analysis**: Profiles are analyzed to extract metrics like execution time, memory, call counts, etc.
4. **MCP Tools**: Analysis results are exposed as MCP tools that Claude can invoke
5. **AI Insights**: Claude uses the tools to answer your questions and provide recommendations

## 🔧 Development

### Running Tests

```bash
./vendor/bin/pest
```

### Project Structure

```
spx-mcp-server/
├── src/
│   ├── Mcp/
│   │   ├── SPX/
│   │   │   └── ProfileParser.php    # Core profile parsing logic
│   │   ├── Tools/                    # Individual MCP tools
│   │   └── McpServer.php             # MCP server implementation
│   ├── Console/
│   │   ├── StartCommand.php          # Start MCP server command
│   │   └── InstallCommand.php        # Installation helper
│   └── SPXMcpServiceProvider.php     # Laravel service provider
├── config/
│   └── spx-mcp.php                   # Configuration file
└── tests/                             # Test suite
```

## 🐛 Troubleshooting

**SPX profiles not being created?**
- Verify SPX is installed: `php -m | grep spx`
- Check your `php.ini` configuration
- Ensure `spx.data_dir` is configured and writable
- Refer to [PHP SPX documentation](https://github.com/NoiseByNorthwest/php-spx)

**MCP server not connecting?**
- Verify the path in your Claude Code config is correct
- Check that the server starts: `php artisan spx-mcp:start`
- Review logs in `storage/logs/laravel.log`

**No profiles showing in Claude?**
- Verify profiles exist in your SPX data directory
- Check the `SPX_DATA_DIR` environment variable matches your `php.ini` setting
- Ensure profiles have `.txt.gz` extension

## 📝 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## 🙏 Credits

- Built on [PHP SPX](https://github.com/NoiseByNorthwest/php-spx) by NoiseByNorthwest
- Uses Laravel's [MCP package](https://github.com/laravel/mcp)
- Created by [Mathias Hansen](https://codemonkey.io)

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

Made with ❤️ by developers who hate performance issues
