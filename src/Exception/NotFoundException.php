<?php

declare(strict_types=1);

namespace Archilan\OrchestratorClient\Exception;

/**
 * The orchestrator answered 404 (story 38.8 review). A missing session is the case callers usually
 * meet ({@see SessionNotFoundException}); a missing endpoint - an orchestrator older than the
 * client - is another.
 */
class NotFoundException extends OrchestratorException
{
}
