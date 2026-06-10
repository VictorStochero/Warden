<?php

namespace VictorStochero\Warden\Console\Demo;

use RuntimeException;

/**
 * Throwaway exception used by warden:demo to generate a single, stable
 * exception event (and therefore one issue on the parent) for testing.
 */
class DemoException extends RuntimeException {}
