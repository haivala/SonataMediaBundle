<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Block;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService;
use Sonata\BlockBundle\Block\Service\EditableBlockService;
use Sonata\BlockBundle\Form\Mapper\FormMapper;
use Sonata\BlockBundle\Meta\Metadata;
use Sonata\BlockBundle\Meta\MetadataInterface;
use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\Doctrine\Model\ManagerInterface;
use Sonata\Form\Type\ImmutableArrayType;
use Sonata\Form\Validator\ErrorElement;
use Sonata\MediaBundle\Admin\BaseMediaAdmin;
use Sonata\MediaBundle\Model\MediaInterface;
use Sonata\MediaBundle\Provider\Pool;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

/**
 * @final since sonata-project/media-bundle 3.21.0
 *
 * @author Thomas Rabaix <thomas.rabaix@sonata-project.org>
 */
class MediaBlockService extends AbstractBlockService implements EditableBlockService
{
    /**
     * @var BaseMediaAdmin
     */
    protected $mediaAdmin;

    /**
     * @var ManagerInterface
     */
    protected $mediaManager;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * NEXT_MAJOR: Remove `$templating` argument.
     *
     * @param Environment|string $twigOrName
     */
    public function __construct(
        $twigOrName,
        ?EngineInterface $templating,
        ContainerInterface $container,
        ManagerInterface $mediaManager
    ) {
        parent::__construct($twigOrName, $templating);

        $this->mediaManager = $mediaManager;
        $this->container = $container;
    }

    /**
     * @return Pool
     */
    public function getMediaPool()
    {
        return $this->getMediaAdmin()->getPool();
    }

    /**
     * @return BaseMediaAdmin
     */
    public function getMediaAdmin()
    {
        if (!$this->mediaAdmin) {
            $this->mediaAdmin = $this->container->get('sonata.media.admin.media');
        }

        return $this->mediaAdmin;
    }

    /**
     * {@inheritdoc}
     */
    public function configureSettings(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'media' => false,
            'title' => null,
            'translation_domain' => null,
            'icon' => null,
            'class' => null,
            'context' => false,
            'mediaId' => null,
            'format' => false,
            'template' => '@SonataMedia/Block/block_media.html.twig',
        ]);
    }

    public function configureCreateForm(FormMapper $formMapper, BlockInterface $block)
    {
        $this->configureEditForm($formMapper, $block);
    }

    public function configureEditForm(FormMapper $formMapper, BlockInterface $block)
    {
        if (!$block->getSetting('mediaId') instanceof MediaInterface) {
            $this->load($block);
        }

        $formatChoices = $this->getFormatChoices($block->getSetting('mediaId'));

        $formMapper->add('settings', ImmutableArrayType::class, [
            'keys' => [
                ['title', TextType::class, [
                    'label' => 'form.label_title',
                    'required' => false,
                ]],
                ['translation_domain', TextType::class, [
                    'label' => 'form.label_translation_domain',
                    'required' => false,
                ]],
                ['icon', TextType::class, [
                    'label' => 'form.label_icon',
                    'required' => false,
                ]],
                ['class', TextType::class, [
                    'label' => 'form.label_class',
                    'required' => false,
                ]],
                [$this->getMediaBuilder($formMapper), null, []],
                ['format', ChoiceType::class, [
                    'required' => \count($formatChoices) > 0,
                    'choices' => $formatChoices,
                    'label' => 'form.label_format',
                ]],
            ],
            'translation_domain' => 'SonataMediaBundle',
        ]);
    }

    /**
     * NEXT_MAJOR: Remove this method.
     */
    public function buildCreateForm(FormMapper $formMapper, BlockInterface $block)
    {
        if ('sonata_deprecation_mute' !== (\func_get_args()[2] ?? null)) {
            @trigger_error(sprintf(
                'Method %s() is deprecated since sonata-project/media-bundle 3.x and will be removed in version 4.0. Use %s::configureCreateForm() instead.',
                __METHOD__,
                static::class,
            ), E_USER_DEPRECATED);
        }
        $this->configureCreateForm($formMapper, $block);
    }

    /**
     * NEXT_MAJOR: Remove this method.
     */
    public function buildEditForm(FormMapper $formMapper, BlockInterface $block)
    {
        if ('sonata_deprecation_mute' !== (\func_get_args()[2] ?? null)) {
            @trigger_error(sprintf(
                'Method %s() is deprecated since sonata-project/media-bundle 3.x and will be removed in version 4.0. Use %s::configureEditForm() instead.',
                __METHOD__,
                static::class,
            ), E_USER_DEPRECATED);
        }
        $this->configureEditForm($formMapper, $block);
    }

    /**
     * NEXT_MAJOR: Remove this method.
     */
    public function validateBlock(ErrorElement $errorElement, BlockInterface $block)
    {
        if ('sonata_deprecation_mute' !== (\func_get_args()[2] ?? null)) {
            @trigger_error(sprintf(
                'Method %s() is deprecated since sonata-project/media-bundle 3.x and will be removed in version 4.0. Use %s::validate() instead.',
                __METHOD__,
                static::class,
            ), E_USER_DEPRECATED);
        }
        $this->validate($errorElement, $block);
    }

    public function validate(ErrorElement $errorElement, BlockInterface $block)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function execute(BlockContextInterface $blockContext, Response $response = null)
    {
        // make sure we have a valid format
        $media = $blockContext->getBlock()->getSetting('mediaId');
        if ($media instanceof MediaInterface) {
            $choices = $this->getFormatChoices($media);

            if (!\array_key_exists($blockContext->getSetting('format'), $choices)) {
                $blockContext->setSetting('format', key($choices));
            }
        }

        return $this->renderResponse($blockContext->getTemplate(), [
            'media' => $blockContext->getSetting('mediaId'),
            'block' => $blockContext->getBlock(),
            'settings' => $blockContext->getSettings(),
        ], $response);
    }

    /**
     * {@inheritdoc}
     */
    public function load(BlockInterface $block)
    {
        $media = $block->getSetting('mediaId', null);

        if (\is_int($media)) {
            $media = $this->mediaManager->findOneBy(['id' => $media]);
        }

        $block->setSetting('mediaId', $media);
    }

    /**
     * {@inheritdoc}
     */
    public function prePersist(BlockInterface $block)
    {
        $block->setSetting('mediaId', \is_object($block->getSetting('mediaId')) ? $block->getSetting('mediaId')->getId() : null);
    }

    /**
     * {@inheritdoc}
     */
    public function preUpdate(BlockInterface $block)
    {
        $block->setSetting('mediaId', \is_object($block->getSetting('mediaId')) ? $block->getSetting('mediaId')->getId() : null);
    }

    public function getMetadata(): MetadataInterface
    {
        return new Metadata($this->getName(), null, null, 'SonataMediaBundle', [
            'class' => 'fa fa-picture-o',
        ]);
    }

    /**
     * NEXT_MAJOR: Remove this method.
     */
    public function getBlockMetadata($code = null)
    {
        if ('sonata_deprecation_mute' !== (\func_get_args()[2] ?? null)) {
            @trigger_error(sprintf(
                'Method %s() is deprecated since sonata-project/media-bundle 3.x and will be removed in version 4.0. Use %s::getMetadata() instead.',
                __METHOD__,
                static::class,
            ), E_USER_DEPRECATED);
        }

        return $this->getMetaData();
    }

    /**
     * @return array
     */
    protected function getFormatChoices(MediaInterface $media = null)
    {
        $formatChoices = [];

        if (!$media instanceof MediaInterface) {
            return $formatChoices;
        }

        $formats = $this->getMediaPool()->getFormatNamesByContext($media->getContext());

        foreach ($formats as $code => $format) {
            $formatChoices[$code] = $code;
        }

        return $formatChoices;
    }

    /**
     * @return FormBuilder
     */
    protected function getMediaBuilder(FormMapper $formMapper)
    {
        // simulate an association ...
        $fieldDescription = $this->getMediaAdmin()->getModelManager()->getNewFieldDescriptionInstance($this->mediaAdmin->getClass(), 'media', [
            'translation_domain' => 'SonataMediaBundle',
        ]);
        $fieldDescription->setAssociationAdmin($this->getMediaAdmin());
        $fieldDescription->setAdmin($this->container->get('sonata.page.admin.block'));
        $fieldDescription->setOption('edit', 'list');
        $fieldDescription->setAssociationMapping([
            'fieldName' => 'media',
            'type' => ClassMetadataInfo::MANY_TO_ONE,
        ]);

        return $formMapper->create('mediaId', ModelListType::class, [
            'sonata_field_description' => $fieldDescription,
            'class' => $this->getMediaAdmin()->getClass(),
            'model_manager' => $this->getMediaAdmin()->getModelManager(),
            'label' => 'form.label_media',
        ]);
    }
}
