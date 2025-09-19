# SPX Profile File Format

## Overview

SPX stores profiling data in two files per profiling session:

1. **`.json`** - Metadata file containing execution context and configuration
2. **`.txt.gz`** - Gzip-compressed event data containing function calls and metrics

Files are stored in `/tmp/spx` by default (configurable via `spx.data_dir`) and named:

```
spx-full-{YYYYMMDD_HHMMSS}-{hostname}-{pid}-{random}
```

## .txt.gz File Structure

The compressed file contains plain text with two sections:

### [events] Section

Each line represents a function call event with space-separated values:

```
<func_idx> <start_flag> <metric_1> <metric_2> ... <metric_n>
```

- **func_idx**: 0-based index referencing the function in the `[functions]` section
- **start_flag**: `1` for function entry, `0` for function exit
- **metric values**: Floating-point cumulative values (4 decimal precision)

Example:

```
[events]
0 1 0.0000 0.0000 1024.0000
1 1 50.1234 45.2341 2048.0000
1 0 125.4567 120.3456 3072.0000
0 0 200.7890 195.6789 3072.0000
```

### [functions] Section

Lists all unique functions called during profiling, one per line:

```
[functions]
main
PDO::__construct
PDO::prepare
PDOStatement::execute
MyClass::processData
```

- Method format: `ClassName::methodName`
- Function format: `functionName`
- Line number corresponds to function index (line 0 = index 0)

## Metrics

Common metrics (when enabled):

| Key   | Description       | Unit         |
|-------|-------------------|--------------|
| `wt`  | Wall time         | microseconds |
| `ct`  | CPU time          | microseconds |
| `it`  | Idle time         | microseconds |
| `mu`  | Memory usage      | bytes        |
| `pmu` | Peak memory usage | bytes        |
| `mor` | Memory own RSS    | bytes        |
| `io`  | I/O total         | bytes        |
| `ior` | I/O reads         | bytes        |
| `iow` | I/O writes        | bytes        |

The `.json` metadata file contains `enabled_metrics` indicating which metrics are present.

## Parsing Logic

1. Events are processed sequentially to maintain call stack
2. Function entry events push to stack with start metrics
3. Function exit events pop from stack and calculate:
    - **Inclusive time**: Total time in function including children
    - **Exclusive time**: Time in function excluding children
4. Parent frames track cumulative child execution time
