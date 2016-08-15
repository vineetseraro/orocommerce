<?php

namespace OroB2B\Bundle\PricingBundle\Tests\Unit\Validator\Constraints;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

use OroB2B\Bundle\PricingBundle\Validator\Constraints\PriceRuleExpression;
use OroB2B\Bundle\PricingBundle\Expression\ExpressionLanguageConverter;
use OroB2B\Bundle\PricingBundle\Expression\ExpressionParser;
use OroB2B\Bundle\PricingBundle\Provider\PriceRuleFieldsProvider;
use OroB2B\Bundle\PricingBundle\Validator\Constraints\PriceRuleExpressionValidator;
use OroB2B\Bundle\ProductBundle\Entity\Product;

class PriceRuleExpressionValidatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ExpressionParser
     */
    protected $parser;

    /**
     * @var PriceRuleFieldsProvider|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $fieldsProvider;

    /**
     * @var PriceRuleExpressionValidator
     */
    protected $expressionValidator;

    protected function setUp()
    {
        $this->fieldsProvider = $this->getMockBuilder(PriceRuleFieldsProvider::class)
            ->disableOriginalConstructor()
            ->getMock();
        $expressionConverter = new ExpressionLanguageConverter($this->fieldsProvider);
        $this->parser = new ExpressionParser($expressionConverter);
        $this->parser->addNameMapping('product', Product::class);
        $this->expressionValidator = new PriceRuleExpressionValidator($this->parser, $this->fieldsProvider);
    }

    /**
     * @dataProvider validateSuccessDataProvider
     * @param string $value
     * @param array $attributes
     */
    public function testValidateSuccess($value, array $attributes)
    {
        $this->fieldsProvider->method('getFields')->willReturn($attributes);

        /** @var ExecutionContextInterface|\PHPUnit_Framework_MockObject_MockObject $context */
        $context = $this->getMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('addViolation');
        
        $this->doTestValidation($value, $context);
    }

    /**
     * @dataProvider validateErrorDataProvider
     * @param string $value
     * @param array $attributes
     */
    public function testValidateError($value, array $attributes)
    {
        $this->fieldsProvider->method('getFields')->willReturn($attributes);

        /** @var ExecutionContextInterface|\PHPUnit_Framework_MockObject_MockObject $context */
        $context = $this->getMock(ExecutionContextInterface::class);
        $context->expects($this->once())->method('addViolation');

        $this->doTestValidation($value, $context);
    }

    /**
     * @param string $value
     * @param ExecutionContextInterface $context
     */
    protected function doTestValidation($value, ExecutionContextInterface $context)
    {
        /** @var PriceRuleExpression|\PHPUnit_Framework_MockObject_MockObject $constraint * */
        $constraint = $this->getMockBuilder(PriceRuleExpression::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->expressionValidator->initialize($context);
        $this->expressionValidator->validate($value, $constraint);
    }

    /**
     * @return array
     */
    public function validateSuccessDataProvider()
    {
        return [
            ['', []],
            [null, []],
            ['product.msrp.value + 1', ['value']],
        ];
    }

    /**
     * @return array
     */
    public function validateErrorDataProvider()
    {
        return [
            ['xxx', []],
            ['product.sku == SKU"', ['sku', 'msrp']],
            ['product.msrp.value + 1', []],
        ];
    }
}
