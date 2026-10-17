<?php

namespace App\Controller;

use App\Entity\FormWidget;
use App\Entity\RegistrationForm;
use App\Enum\FormWidgetKind;
use App\Form\FormWidgetAdminType;
use App\Form\FormWidgetViewType;
use App\Repository\FormWidgetRepository;
use App\Repository\RegistrationFormRepository;
use App\Service\NativeWidgets;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin library of the widgets registration forms are made of.
 */
class FormWidgetController extends AbstractController
{
    #[Route('/admin/widgets', name: 'admin.form_widget.index')]
    public function index(FormWidgetRepository $repository, RegistrationFormRepository $formRepository, NativeWidgets $natives): Response
    {
        $natives->all();
        $widgets = $repository->findAllOrdered();
        $usages = [];
        foreach ($widgets as $widget) {
            $usages[$widget->getId()] = [
                'widgets' => $repository->findReferencing($widget),
                'forms' => $formRepository->findBy(['widget' => $widget]),
            ];
        }

        // page rendering
        return $this->render('form_widget/index.html.twig', [
            'natives' => array_filter($widgets, fn (FormWidget $widget) => $widget->isNative()),
            'widgets' => array_filter($widgets, fn (FormWidget $widget) => !$widget->isNative()),
            'usages' => $usages,
        ]);
    }

    #[Route('/admin/widgets/new', name: 'admin.form_widget.new')]
    public function new(Request $request, EntityManagerInterface $em, NativeWidgets $natives): Response
    {
        $natives->all();
        // natives are fixed: only lists and composed widgets are created
        $widget = new FormWidget();
        $widget->setKind(FormWidgetKind::LIST->value === $request->query->getString('kind') ? FormWidgetKind::LIST : FormWidgetKind::COMPOSED);

        // form create and handling
        $form = $this->createForm(FormWidgetAdminType::class, $widget);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($widget);
            $em->flush();
            $this->addFlash('success', 'Le widget a été créé.');

            return $this->redirectToRoute('admin.form_widget.edit', ['id' => $widget->getId()]);
        }

        // page rendering
        return $this->render('form_widget/edit.html.twig', [
            'widget' => null,
            'form' => $form,
            'preview' => null,
            'usage' => null,
        ]);
    }

    #[Route('/admin/widgets/{id}/edit', name: 'admin.form_widget.edit', requirements: ['id' => '\d+'])]
    public function edit(FormWidget $widget, Request $request, EntityManagerInterface $em, FormWidgetRepository $repository, RegistrationFormRepository $formRepository, NativeWidgets $natives): Response
    {
        if ($widget->isNative()) {
            throw $this->createNotFoundException('Les champs de base ne sont pas modifiables.');
        }
        $natives->all();
        // form create and handling
        $form = $this->createForm(FormWidgetAdminType::class, $widget);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Le widget a été enregistré.');

            return $this->redirectToRoute('admin.form_widget.edit', ['id' => $widget->getId()]);
        }
        if ($form->isSubmitted()) {
            // the preview shows the saved widget, not the rejected changes
            $em->refresh($widget);
        }

        // page rendering
        return $this->render('form_widget/edit.html.twig', [
            'widget' => $widget,
            'form' => $form,
            'preview' => $this->preview($widget),
            'usage' => $this->usage($widget, $repository, $formRepository),
        ]);
    }

    #[Route('/admin/widgets/{id}', name: 'admin.form_widget.show', requirements: ['id' => '\d+'])]
    public function show(FormWidget $widget): Response
    {
        // page rendering
        return $this->render('form_widget/show.html.twig', [
            'widget' => $widget,
            'preview' => $this->preview($widget),
        ]);
    }

    #[Route('/admin/widgets/{id}/delete', name: 'admin.form_widget.delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(FormWidget $widget, Request $request, EntityManagerInterface $em, FormWidgetRepository $repository, RegistrationFormRepository $formRepository): Response
    {
        if ($widget->isNative()) {
            $this->addFlash('error', 'Les champs de base ne peuvent pas être supprimés.');
        } elseif (!$this->isCsrfTokenValid('delete-form-widget-'.$widget->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');
        } elseif ($repository->findReferencing($widget) || $formRepository->findBy(['widget' => $widget])) {
            $this->addFlash('error', 'Ce widget est utilisé par un autre widget ou un formulaire d\'inscription : retirez-le d\'abord.');
        } else {
            $em->remove($widget);
            $em->flush();
            $this->addFlash('success', 'Le widget a été supprimé.');
        }

        return $this->redirectToRoute('admin.form_widget.index');
    }

    private function preview(FormWidget $widget): FormView
    {
        return $this->createForm(FormWidgetViewType::class, null, [
            'widget' => $widget,
            'csrf_protection' => false,
            'label' => false,
        ])->createView();
    }

    /**
     * Registration forms showing this widget, directly or through other widgets.
     *
     * @return array{forms: RegistrationForm[], registrations: int}
     */
    private function usage(FormWidget $widget, FormWidgetRepository $repository, RegistrationFormRepository $formRepository): array
    {
        $ancestors = [$widget->getId() => $widget];
        $queue = [$widget];
        while ($current = array_shift($queue)) {
            foreach ($repository->findReferencing($current) as $parent) {
                if (!isset($ancestors[$parent->getId()])) {
                    $ancestors[$parent->getId()] = $parent;
                    $queue[] = $parent;
                }
            }
        }

        $forms = $formRepository->findBy(['widget' => array_values($ancestors)]);

        return [
            'forms' => $forms,
            'registrations' => array_sum(array_map(fn (RegistrationForm $form) => $form->getRegistrations()->count(), $forms)),
        ];
    }
}
