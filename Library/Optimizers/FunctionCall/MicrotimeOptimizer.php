<?php

/**
 * This file is part of the Zephir.
 *
 * (c) Zephir Team <team@zephir-lang.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zephir\Optimizers\FunctionCall;

use Zephir\Call;
use Zephir\CompilationContext;
use Zephir\Exception\CompilerException;
use Zephir\CompiledExpression;
use Zephir\Optimizers\OptimizerAbstract;

/**
 * MicrotimeOptimizer
 *
 * Optimizes calls to 'microtime' using internal function
 */
class MicrotimeOptimizer extends OptimizerAbstract
{
    /**
     * @param array $expression
     * @param Call $call
     * @param CompilationContext $context
     * @return bool|CompiledExpression|mixed
     * @throws \Zephir\Exception\CompilerException
     */
    public function optimize(array $expression, Call $call, CompilationContext $context)
    {
        /* microtime has one optional parameter (get_as_float) */
        if (isset($expression['parameters']) && count($expression['parameters']) > 2) {
            return false;
        }

        /**
         * Process the expected symbol to be returned
         */
        $call->processExpectedReturn($context);

        $symbolVariable = $call->getSymbolVariable(true, $context);
        if ($symbolVariable->isNotVariableAndString()) {
            throw new CompilerException("Returned values by functions can only be assigned to variant variables", $expression);
        }

        $context->headersManager->add('kernel/time');

        $symbol = $context->backend->getVariableCode($symbolVariable);
        if (!isset($expression['parameters'])) {
            $symbolVariable->setDynamicTypes('string');
            if ($call->mustInitSymbolVariable()) {
                $symbolVariable->initVariant($context);
            }
            $context->codePrinter->output('zephir_microtime(' . $symbol . ', NULL TSRMLS_CC);');
        } else {
            $symbolVariable->setDynamicTypes('double');
            $resolvedParams = $call->getReadOnlyResolvedParams($expression['parameters'], $context, $expression);
            if ($call->mustInitSymbolVariable()) {
                $symbolVariable->initVariant($context);
            }
            $context->codePrinter->output('zephir_microtime(' . $symbol . ', ' . $resolvedParams[0] . ' TSRMLS_CC);');
        }
        return new CompiledExpression('variable', $symbolVariable->getRealName(), $expression);
    }
}
