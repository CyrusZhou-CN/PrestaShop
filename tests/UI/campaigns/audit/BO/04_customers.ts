import {expect} from 'chai';
import addCustomerPage from '@pages/BO/customers/add';
import viewCustomerPage from '@pages/BO/customers/view';
import addressesPage from '@pages/BO/customers/addresses';
import addAddressPage from '@pages/BO/customers/addresses/add';
import testContext from '@utils/testContext';

import {
  boCustomersPage,
  boDashboardPage,
  boLoginPage,
  type BrowserContext,
  type Page,
  utilsPlaywright,
} from '@prestashop-core/ui-testing';

const baseContext: string = 'audit_BO_customers';

describe('BO - Customers', async () => {
  let browserContext: BrowserContext;
  let page: Page;

  before(async function () {
    utilsPlaywright.setErrorsCaptured(true);

    browserContext = await utilsPlaywright.createBrowserContext(this.browser);
    page = await utilsPlaywright.newTab(browserContext);
  });

  after(async () => {
    await utilsPlaywright.closeBrowserContext(browserContext);
  });

  it('should login in BO', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'loginBO', baseContext);

    await boLoginPage.goTo(page, global.BO.URL);
    await boLoginPage.successLogin(page, global.BO.EMAIL, global.BO.PASSWD);

    const pageTitle = await boDashboardPage.getPageTitle(page);
    expect(pageTitle).to.contains(boDashboardPage.pageTitle);

    const jsErrors = utilsPlaywright.getJsErrors();
    expect(jsErrors.length).to.equals(0);
  });

  it('should go to \'Customers > Customers\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToCustomersPage', baseContext);

    await boDashboardPage.goToSubMenu(
      page,
      boDashboardPage.customersParentLink,
      boDashboardPage.customersLink,
    );
    await boCustomersPage.closeSfToolBar(page);

    const pageTitle = await boCustomersPage.getPageTitle(page);
    expect(pageTitle).to.contains(boCustomersPage.pageTitle);

    const jsErrors = utilsPlaywright.getJsErrors();
    expect(jsErrors.length).to.equals(0);
  });

  it('should go to \'Customers > Customers > View Customer\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToCustomerViewPage', baseContext);

    await boCustomersPage.goToViewCustomerPage(page, 1);

    const pageTitle = await viewCustomerPage.getPageTitle(page);
    expect(pageTitle).to.contains(viewCustomerPage.pageTitle('J. DOE'));

    const jsErrors = utilsPlaywright.getJsErrors();
    expect(jsErrors.length).to.equals(0);
  });

  it('should go to \'Customers > Customers > Edit Customer\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToCustomerEditPage', baseContext);

    await viewCustomerPage.goToEditCustomerPage(page);

    const pageTitle = await addCustomerPage.getPageTitle(page);
    expect(pageTitle).to.contains(addCustomerPage.pageTitleEdit);

    const jsErrors = utilsPlaywright.getJsErrors();
    expect(jsErrors.length).to.equals(0);
  });

  it('should go to \'Customers > Customers > New Customer\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToNewCustomerPage', baseContext);

    await boDashboardPage.goToSubMenu(
      page,
      boDashboardPage.customersParentLink,
      boDashboardPage.customersLink,
    );
    await boCustomersPage.goToAddNewCustomerPage(page);

    const pageTitle = await addCustomerPage.getPageTitle(page);
    expect(pageTitle).to.contains(addCustomerPage.pageTitleCreate);

    const jsErrors = utilsPlaywright.getJsErrors();
    expect(jsErrors.length).to.equals(0);
  });

  it('should go to \'Customers > Addresses\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToAddressesPage', baseContext);

    await boDashboardPage.goToSubMenu(
      page,
      boDashboardPage.customersParentLink,
      boDashboardPage.addressesLink,
    );

    const pageTitle = await addressesPage.getPageTitle(page);
    expect(pageTitle).to.contains(addressesPage.pageTitle);

    const jsErrors = utilsPlaywright.getJsErrors();
    expect(jsErrors.length).to.equals(0);
  });

  it('should go to \'Customers > Addresses > Edit Address\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToAddressEditPage', baseContext);

    await addressesPage.goToEditAddressPage(page, 1);

    const pageTitle = await addAddressPage.getPageTitle(page);
    expect(pageTitle).to.contains(addAddressPage.pageTitleEdit);

    const jsErrors = utilsPlaywright.getJsErrors();
    expect(jsErrors.length).to.equals(0);
  });

  it('should go to \'Customers > Addresses > New Address\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToNewAddressPage', baseContext);

    await boDashboardPage.goToSubMenu(
      page,
      boDashboardPage.customersParentLink,
      boDashboardPage.addressesLink,
    );
    await addressesPage.goToAddNewAddressPage(page);

    const pageTitle = await addAddressPage.getPageTitle(page);
    expect(pageTitle).to.contains(addAddressPage.pageTitleCreate);

    const jsErrors = utilsPlaywright.getJsErrors();
    expect(jsErrors.length).to.equals(0);
  });
});
