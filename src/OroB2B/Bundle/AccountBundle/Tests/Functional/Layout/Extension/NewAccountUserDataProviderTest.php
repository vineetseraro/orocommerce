<?php

namespace OroB2B\Bundle\AccountBundle\Tests\Functional\Layout\Extension;

use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\Layout\LayoutContext;

use OroB2B\Bundle\AccountBundle\Layout\Extension\NewAccountUserDataProvider;

/**
 * @dbIsolation
 */
class NewAccountUserDataProviderTest extends WebTestCase
{
    /** @var LayoutContext */
    protected $context;

    /** @var NewAccountUserDataProvider */
    protected $dataProvider;

    protected function setUp()
    {
        $this->initClient();

        $this->context = new LayoutContext();
        $this->dataProvider = $this->getContainer()->get('orob2b_account.layout.data_provider.new_account_user');
    }

    public function testGetData()
    {
        $actual = $this->dataProvider->getData($this->context);

        $this->assertEquals(null, $actual->getId());
        $this->assertEquals('ROLE_FRONTEND_BUYER', $actual->getRoles()[0]->getRole());
        $this->assertEquals('admin', $actual->getOwner()->getUsername());
        $this->assertEquals('OroCRM', $actual->getOrganization()->getName());
    }
}
