import {expect} from 'chai';
import addOrderPage from '@pages/BO/orders/add';
import creditSlipsPage from '@pages/BO/orders/creditSlips';
import deliverySlipsPage from '@pages/BO/orders/deliverySlips';
import invoicesPage from '@pages/BO/orders/invoices';
import testContext from '@utils/testContext';

import {
  boDashboardPage,
  boLoginPage,
  boOrdersPage,
  boOrdersViewBlockTabListPage,
  boShoppingCartsPage,
  type BrowserContext,
  type Page,
  utilsPlaywright,
} from '@prestashop-core/ui-testing';

const baseContext: string = 'audit_BO_orders';

describe('BO - Orders', async () => {
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

  it('should go to \'Orders > Orders\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToOrdersPage', baseContext);

    await boDashboardPage.goToSubMenu(
      page,
      boDashboardPage.ordersParentLink,
      boDashboardPage.ordersLink,
    );

    const pageTitle = await boOrdersPage.getPageTitle(page);
    expect(pageTitle).to.contains(boOrdersPage.pageTitle);

    const jsErrors = utilsPlaywright.getJsErrors();
    expect(jsErrors.length).to.equals(0);
  });

  it('should go to \'Orders > Orders > New Order\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToCreateOrderPage', baseContext);

    await boOrdersPage.goToCreateOrderPage(page);

    const pageTitle = await addOrderPage.getPageTitle(page);
    expect(pageTitle).to.contains(addOrderPage.pageTitle);

    const jsErrors = utilsPlaywright.getJsErrors();
    expect(jsErrors.length).to.equals(0);
  });

  it('should go to \'Orders > Invoices\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToInvoicesPage', baseContext);

    await boDashboardPage.goToSubMenu(
      page,
      boDashboardPage.ordersParentLink,
      boDashboardPage.invoicesLink,
    );
    await invoicesPage.closeSfToolBar(page);

    const pageTitle = await invoicesPage.getPageTitle(page);
    expect(pageTitle).to.contains(invoicesPage.pageTitle);

    const jsErrors = utilsPlaywright.getJsErrors();
    expect(jsErrors.length).to.equals(0);
  });

  it('should go to \'Orders > Credit slips\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToCreditSlipsPage', baseContext);

    await boDashboardPage.goToSubMenu(
      page,
      boDashboardPage.ordersParentLink,
      boDashboardPage.creditSlipsLink,
    );
    await creditSlipsPage.closeSfToolBar(page);

    const pageTitle = await creditSlipsPage.getPageTitle(page);
    expect(pageTitle).to.contains(creditSlipsPage.pageTitle);

    const jsErrors = utilsPlaywright.getJsErrors();
    expect(jsErrors.length).to.equals(0);
  });

  it('should go to \'Orders > Delivery slips\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToDeliverySlipsPage', baseContext);

    await boOrdersViewBlockTabListPage.goToSubMenu(
      page,
      boOrdersViewBlockTabListPage.ordersParentLink,
      boOrdersViewBlockTabListPage.deliverySlipslink,
    );
    await deliverySlipsPage.closeSfToolBar(page);

    const pageTitle = await deliverySlipsPage.getPageTitle(page);
    expect(pageTitle).to.contains(deliverySlipsPage.pageTitle);

    const jsErrors = utilsPlaywright.getJsErrors();
    expect(jsErrors.length).to.equals(0);
  });

  it('should go to \'Orders > Shopping carts\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToShoppingCartsPage', baseContext);

    await boDashboardPage.goToSubMenu(
      page,
      boDashboardPage.ordersParentLink,
      boDashboardPage.shoppingCartsLink,
    );

    const pageTitle = await boShoppingCartsPage.getPageTitle(page);
    expect(pageTitle).to.contains(boShoppingCartsPage.pageTitle);

    const jsErrors = utilsPlaywright.getJsErrors();
    expect(jsErrors.length).to.equals(0);
  });
});
