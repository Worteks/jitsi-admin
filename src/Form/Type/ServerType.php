<?php
/**
 * Created by PhpStorm.
 * User: Emanuel
 * Date: 17.09.2019
 * Time: 20:29
 */

namespace App\Form\Type;


use App\Entity\AuditTomAbteilung;
use App\Entity\Server;

use App\Entity\KeycloakGroupsToServers;

use League\CommonMark\Inline\Element\Text;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Symfony\Component\Form\Extension\Core\Type\CollectionType;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $builder
            ->add('url', TextType::class, ['required' => true, 'label' => 'lable.serverUrl', 'translation_domain' => 'form', 'help' => 'help.serverUrl'])
            ->add('appId', TextType::class, ['required' => false, 'label' => 'label.appId', 'translation_domain' => 'form'])
            ->add('appSecret', TextType::class, ['required' => false, 'label' => 'label.appSecret', 'translation_domain' => 'form'])

            ->add('keycloakGroups', CollectionType::class,
                ['entry_type' => KeycloakGroupsToServersType::class,
                'entry_options' => ['label' => 'false',],
                'allow_add' => true,
                'allow_delete' =>true,
                'by_reference' => false,
                'label' => false,
                'translation_domain' => 'form',])

            ->add('url', TextType::class, ['required' => true, 'label' => 'lable.serverUrl', 'translation_domain' => 'form', 'help' => 'help.serverUrl'])
            ->add('licenseKey', TextType::class, ['required' => false, 'label' => 'label.serverLicenseKey', 'translation_domain' => 'form'])
            ->add('featureEnableByJWT', CheckboxType::class, ['required' => false, 'label' => 'label.featureEnalbeByJwt','help'=>'help.featureEnalbeByJwt', 'translation_domain' => 'form'])
            ->add('submit', SubmitType::class, ['attr' => array('class' => 'btn btn-outline-primary'), 'label' => 'label.speichern', 'translation_domain' => 'form']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
          'data_class' => Server::class,
        ]);

    }
}
