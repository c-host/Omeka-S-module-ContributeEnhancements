<?php declare(strict_types=1);

namespace ContributeEnhancements\View\Helper;

use Contribute\Form\SendMessageForm;
use Laminas\Form\FormElementManager;
use Laminas\View\Helper\AbstractHelper;

class SendMessageDialog extends AbstractHelper
{
    protected FormElementManager $formManager;

    protected bool $rendered = false;

    public function __construct(FormElementManager $formManager)
    {
        $this->formManager = $formManager;
    }

    public function __invoke(): string
    {
        if ($this->rendered) {
            return '';
        }

        $this->rendered = true;

        /** @var SendMessageForm $form */
        $form = $this->formManager->get(SendMessageForm::class);
        $form->init();

        return (string) $this->getView()->partial('common/dialog/contribution-send-message', [
            'form' => $form,
        ]);
    }
}
