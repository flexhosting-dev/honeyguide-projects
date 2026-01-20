<?php

namespace App\Form;

use App\Entity\Milestone;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class TaskFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Project|null $project */
        $project = $options['project'];

        // Add milestone selector if project is provided
        if ($project !== null) {
            $builder->add('milestone', EntityType::class, [
                'label' => 'Milestone',
                'class' => Milestone::class,
                'choices' => $project->getMilestones(),
                'choice_label' => 'name',
                'attr' => [
                    'class' => 'block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please select a milestone']),
                ],
            ]);
        }

        $builder
            ->add('title', TextType::class, [
                'label' => 'Task Title',
                'attr' => [
                    'class' => 'block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6',
                    'placeholder' => 'Enter task title',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a task title']),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6',
                    'placeholder' => 'Describe this task',
                    'rows' => 3,
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'To Do' => TaskStatus::TODO,
                    'In Progress' => TaskStatus::IN_PROGRESS,
                    'In Review' => TaskStatus::IN_REVIEW,
                    'Completed' => TaskStatus::COMPLETED,
                ],
                'attr' => [
                    'class' => 'block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6',
                ],
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Priority',
                'choices' => [
                    'None' => TaskPriority::NONE,
                    'Low' => TaskPriority::LOW,
                    'Medium' => TaskPriority::MEDIUM,
                    'High' => TaskPriority::HIGH,
                ],
                'attr' => [
                    'class' => 'block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6',
                ],
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Start Date',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'class' => 'block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6',
                ],
            ])
            ->add('dueDate', DateType::class, [
                'label' => 'Due Date',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'class' => 'block w-full rounded-md border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
            'project' => null,
        ]);

        $resolver->setAllowedTypes('project', ['null', Project::class]);
    }
}
