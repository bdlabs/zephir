<?php

/*
 * This file is part of the Zephir.
 *
 * (c) Zephir Team <team@zephir-lang.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zephir\Statements;

use Zephir\CompilationContext;
use Zephir\Exception\CompilerException;
use Zephir\Expression;
use function Zephir\add_slashes;

/**
 * ReturnStatement.
 *
 * Return statement is used to assign variables
 */
class ReturnStatement extends StatementAbstract
{
    /**
     * @param CompilationContext $compilationContext
     *
     * @throws CompilerException
     */
    public function compile(CompilationContext $compilationContext)
    {
        $statement = $this->statement;

        $codePrinter = $compilationContext->codePrinter;

        if (isset($statement['expr'])) {
            $currentMethod = $compilationContext->currentMethod;

            if ($currentMethod->isConstructor()) {
                throw new CompilerException('Constructors cannot return values', $statement['expr']);
            }

            if ($currentMethod->isVoid()) {
                throw new CompilerException("Method is marked as 'void' and it must not return any value", $statement['expr']);
            }

            /*
             * Use return member for properties on this
             */
            if ('property-access' == $statement['expr']['type']) {
                if ('variable' == $statement['expr']['left']['type']) {
                    if ('this' == $statement['expr']['left']['value']) {
                        if ('variable' == $statement['expr']['right']['type']) {
                            /**
                             * If the property is accessed on 'this', we check if the property does exist.
                             */
                            $property = $statement['expr']['right']['value'];
                            $classDefinition = $compilationContext->classDefinition;
                            if (!$classDefinition->hasProperty($property)) {
                                throw new CompilerException("Class '".$classDefinition->getCompleteName()."' does not have a property called: '".$property."'", $statement['expr']['right']);
                            }

                            $compilationContext->headersManager->add('kernel/object');
                            $codePrinter->output('RETURN_MM_MEMBER(getThis(), "'.$property.'");');

                            return;
                        }
                    }
                }
            }

            /**
             * Fetches return_value and tries to return the value directly there.
             */
            $variable = $compilationContext->symbolTable->getVariable('return_value');

            $expr = new Expression($statement['expr']);
            $expr->setExpectReturn(true, $variable);
            $expr->setReadOnly(true);
            $resolvedExpr = $expr->compile($compilationContext);

            /*
             * Here we check if the variable returns a compatible type according to its type hints
             */
            if ($currentMethod->hasReturnTypes()) {
                switch ($resolvedExpr->getType()) {
                    case 'null':
                        if (!$currentMethod->areReturnTypesNullCompatible()) {
                            throw new CompilerException('Returning type: '.$resolvedExpr->getType().' but this type is not compatible with return-type hints declared in the method', $statement['expr']);
                        }
                        break;

                    case 'int':
                    case 'uint':
                    case 'long':
                    case 'char':
                    case 'uchar':
                        if (!$currentMethod->areReturnTypesIntCompatible()) {
                            throw new CompilerException('Returning type: '.$resolvedExpr->getType().' but this type is not compatible with return-type hints declared in the method', $statement['expr']);
                        }
                        break;

                    case 'bool':
                        if (!$currentMethod->areReturnTypesBoolCompatible()) {
                            throw new CompilerException('Returning type: '.$resolvedExpr->getType().' but this type is not compatible with return-type hints declared in the method', $statement['expr']);
                        }
                        break;

                    case 'double':
                        if (!$currentMethod->areReturnTypesDoubleCompatible()) {
                            throw new CompilerException('Returning type: '.$resolvedExpr->getType().' but this type is not compatible with return-type hints declared in the method', $statement['expr']);
                        }
                        break;

                    case 'string':
                        if (!$currentMethod->areReturnTypesStringCompatible()) {
                            throw new CompilerException('Returning type: '.$resolvedExpr->getType().' but this type is not compatible with return-type hints declared in the method', $statement['expr']);
                        }
                        break;

                    case 'variable':
                        $symbolVariable = $compilationContext->symbolTable->getVariableForRead($resolvedExpr->getCode(), $compilationContext, $statement['expr']);
                        switch ($symbolVariable->getType()) {
                            case 'int':
                            case 'uint':
                            case 'long':
                            case 'char':
                            case 'uchar':
                                if (!$currentMethod->areReturnTypesIntCompatible()) {
                                    throw new CompilerException('Returning type: '.$resolvedExpr->getType().' but this type is not compatible with return-type hints declared in the method', $statement['expr']);
                                }
                                break;

                            case 'double':
                                if (!$currentMethod->areReturnTypesDoubleCompatible()) {
                                    throw new CompilerException('Returning type: '.$resolvedExpr->getType().' but this type is not compatible with return-type hints declared in the method', $statement['expr']);
                                }
                                break;

                            case 'string':
                                if (!$currentMethod->areReturnTypesStringCompatible()) {
                                    throw new CompilerException('Returning type: '.$resolvedExpr->getType().' but this type is not compatible with return-type hints declared in the method', $statement['expr']);
                                }
                                break;

                            case 'bool':
                                if (!$currentMethod->areReturnTypesBoolCompatible()) {
                                    throw new CompilerException('Returning type: '.$resolvedExpr->getType().' but this type is not compatible with return-type hints declared in the method', $statement['expr']);
                                }
                                break;

                            case 'variable':
                                break;
                        }
                        break;
                }
            }

            switch ($resolvedExpr->getType()) {
                case 'null':
                    $codePrinter->output('RETURN_MM_NULL();');
                    break;

                case 'int':
                case 'uint':
                case 'long':
                case 'char':
                case 'uchar':
                    $codePrinter->output('RETURN_MM_LONG('.$resolvedExpr->getCode().');');
                    break;

                case 'bool':
                    $codePrinter->output('RETURN_MM_BOOL('.$resolvedExpr->getBooleanCode().');');
                    break;

                case 'double':
                    $codePrinter->output('RETURN_MM_DOUBLE('.$resolvedExpr->getCode().');');
                    break;

                case 'string':
                case 'istring':
                    $compilationContext->backend->returnString(
                        add_slashes($resolvedExpr->getCode()),
                        $compilationContext
                    );
                    break;

                case 'array':
                    if ('return_value' != $resolvedExpr->getCode()) {
                        $codePrinter->output('RETURN_CTOR('.$resolvedExpr->getCode().');');
                    } else {
                        $codePrinter->output('RETURN_MM();');
                    }
                    break;

                case 'variable':
                    if (!isset($symbolVariable)) {
                        $symbolVariable = $compilationContext->symbolTable->getVariableForRead($resolvedExpr->getCode(), $compilationContext, $statement['expr']);
                    }

                    switch ($symbolVariable->getType()) {
                        case 'int':
                        case 'uint':
                        case 'long':
                        case 'char':
                        case 'uchar':
                            $codePrinter->output('RETURN_MM_LONG('.$symbolVariable->getName().');');
                            break;

                        case 'double':
                            $codePrinter->output('RETURN_MM_DOUBLE('.$symbolVariable->getName().');');
                            break;

                        case 'string':
                        case 'array':
                            $codePrinter->output('RETURN_CTOR('.$compilationContext->backend->getVariableCode($symbolVariable).');');
                            break;

                        case 'bool':
                            $codePrinter->output('RETURN_MM_BOOL('.$symbolVariable->getName().');');
                            break;

                        case 'variable':
                            if ('this_ptr' == $symbolVariable->getName()) {
                                $codePrinter->output('RETURN_THIS();');
                            } else {
                                if ('return_value' != $symbolVariable->getName()) {
                                    if (!$symbolVariable->isExternal()) {
                                        if ($symbolVariable->isLocalOnly()) {
                                            $codePrinter->output('RETURN_LCTOR('.$compilationContext->backend->getVariableCode($symbolVariable).');');
                                        } else {
                                            if (!$symbolVariable->isMemoryTracked()) {
                                                $codePrinter->output('RETURN_CTOR('.$compilationContext->backend->getVariableCode($symbolVariable).');');
                                            } else {
                                                $codePrinter->output('RETURN_CCTOR('.$compilationContext->backend->getVariableCode($symbolVariable).');');
                                            }
                                        }
                                    } else {
                                        $codePrinter->output('RETVAL_ZVAL('.$compilationContext->backend->getVariableCode($symbolVariable).', 1, 0);');
                                        $codePrinter->output('RETURN_MM();');
                                    }
                                } else {
                                    $codePrinter->output('RETURN_MM();');
                                }
                            }
                            if ($symbolVariable->isTemporal()) {
                                $symbolVariable->setIdle(true);
                            }
                            break;

                        default:
                            throw new CompilerException("Cannot return variable '".$symbolVariable->getType()."'", $statement['expr']);
                    }
                    break;

                default:
                    throw new CompilerException("Cannot return '".$resolvedExpr->getType()."'", $statement['expr']);
            }

            return;
        }

        /*
         * Return without an expression
         */
        $codePrinter->output('RETURN_MM_NULL();');
    }
}
