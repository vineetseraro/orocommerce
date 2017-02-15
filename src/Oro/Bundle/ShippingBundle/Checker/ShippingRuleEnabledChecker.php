<?php

namespace Oro\Bundle\ShippingBundle\Checker;

use Oro\Bundle\ShippingBundle\Entity\ShippingMethodsConfigsRule;

class ShippingRuleEnabledChecker implements ShippingRuleEnabledCheckerInterface
{
    /** @var ShippingMethodEnabledByIdentifierCheckerInterface */
    private $methodEnabledChecker;

    /**
     * @param ShippingMethodEnabledByIdentifierCheckerInterface $methodEnabledChecker
     */
    public function __construct(ShippingMethodEnabledByIdentifierCheckerInterface $methodEnabledChecker)
    {
        $this->methodEnabledChecker = $methodEnabledChecker;
    }

    /**
     * @param ShippingMethodsConfigsRule $rule
     *
     * @return bool
     */
    public function canBeEnabled(ShippingMethodsConfigsRule $rule)
    {
        foreach ($rule->getMethodConfigs() as $config) {
            if ($this->methodEnabledChecker->isEnabled($config->getMethod())) {
                return true;
            }
        }

        return false;
    }
}
