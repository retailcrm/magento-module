<?php

namespace Retailcrm\Retailcrm\Test\Helpers;

use Retailcrm\Retailcrm\Test\TestCase;

class FieldsetTest extends TestCase
{
    protected $elementMock;
    protected $authSessionMock;
    protected $userMock;
    protected $requestMock;
    protected $urlModelMock;
    protected $layoutMock;
    protected $helperMock;
    protected $groupMock;
    protected $objectManager;
    protected $context;
    protected $form;
    protected $testElementId = 'test_element_id';
    protected $testFieldSetCss = 'test_fieldset_css';
    protected $objectFactory;
    protected $secureRenderer;

    public function setUp()
    {
        $this->objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
        $factoryMock = $this->createMock(\Magento\Framework\Data\Form\Element\Factory::class);
        $collectionFactoryMock = $this->createMock(\Magento\Framework\Data\Form\Element\CollectionFactory::class);
        $escaperMock = $this->createMock(\Magento\Framework\Escaper::class);
        $formElementMock = $this->createMock(\Magento\Framework\Data\Form\Element\Select::class);
        $factoryMock->expects($this->any())->method('create')->willReturn($formElementMock);
        $this->objectFactory = $this->objectManager->getObject(\Magento\Framework\DataObjectFactory::class);
        $formElementMock->expects($this->any())->method('setRenderer')->willReturn($formElementMock);
        $elementCollection = $this->objectManager->getObject(\Magento\Framework\Data\Form\Element\Collection::class);

        // element mock
        $this->elementMock = $this->getMockBuilder(\Magento\Framework\Data\Form\Element\AbstractElement::class)
            ->setMethods([
                'getId',
                'getHtmlId',
                'getName',
                'getElements',
                'getLegend',
                'getComment',
                'getIsNested',
                'getExpanded',
                'getForm',
                'addField'
            ])
            ->setConstructorArgs([$factoryMock, $collectionFactoryMock, $escaperMock])
            ->getMockForAbstractClass();
        $this->elementMock->expects($this->any())
            ->method('getId')
            ->willReturn($this->testElementId);
        $this->elementMock->expects($this->any())
            ->method('getHtmlId')
            ->willReturn($this->testElementId);
        $this->elementMock->expects($this->any())
            ->method('addField')
            ->willReturn($formElementMock);
        $this->elementMock->expects($this->any())
            ->method('getElements')
            ->willReturn($elementCollection);

        $this->authSessionMock = $this->getMockBuilder(\Magento\Backend\Model\Auth\Session::class)
            ->setMethods(['getUser'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->userMock = $this->getMockBuilder(\Magento\User\Model\User::class)
            ->setMethods(['getExtra'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->authSessionMock->expects($this->any())
            ->method('getUser')
            ->willReturn($this->userMock);

        $this->requestMock = $this->getMockBuilder(\Magento\Framework\App\RequestInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->requestMock->expects($this->any())
            ->method('getParam')
            ->willReturn('Test Param');

        $factoryCollection = $this->createMock(\Magento\Framework\Data\Form\Element\CollectionFactory::class);
        $elementCollection = $this->createMock(\Magento\Framework\Data\Form\Element\Collection::class);
        $factoryCollection->expects($this->any())->method('create')->willReturn($elementCollection);
        $rendererMock = $this->createMock(\Magento\Framework\Data\Form\Element\Renderer\RendererInterface::class);

        $this->secureRenderer = $this->createMock(\Magento\Framework\View\Helper\SecureHtmlRenderer::class);
        $this->secureRenderer->method('renderEventListenerAsTag')
            ->willReturnCallback(
                function (string $event, string $js, string $selector): string {
                    return "<script>document.querySelector('$selector').$event = function () { $js };</script>";
                }
            );
        $this->secureRenderer->method('renderStyleAsTag')
            ->willReturnCallback(
                function (string $style, string $selector): string {
                    return "<style>$selector { $style }</style>";
                }
            );

        $this->urlModelMock = $this->createMock(\Magento\Backend\Model\Url::class);
        $this->layoutMock = $this->createMock(\Magento\Framework\View\Layout::class);
        $this->groupMock = $this->createMock(\Magento\Config\Model\Config\Structure\Element\Group::class);
        $this->groupMock->expects($this->any())->method('getFieldsetCss')
            ->will($this->returnValue($this->testFieldSetCss));
        $this->context = $this->createMock(\Magento\Backend\Block\Context::class);
        $this->context->expects($this->any())->method('getRequest')->willReturn($this->requestMock);
        $this->context->expects($this->any())->method('getUrlBuilder')->willReturn($this->urlModelMock);
        $this->layoutMock->expects($this->any())->method('getBlockSingleton')->willReturn($rendererMock);
        $this->helperMock = $this->createMock(\Magento\Framework\View\Helper\Js::class);
        $this->form = $this->createPartialMock(
            \Magento\Config\Block\System\Config\Form::class,
            //['getElements', 'getRequest']
            ['getRequest']
        );
        //$this->form->expects($this->any())->method('getElements')->willReturn($elementCollection);
        $this->form->expects($this->any())->method('getRequest')->willReturn($this->requestMock);
    }
}
