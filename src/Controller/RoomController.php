<?php

namespace App\Controller;

use App\Entity\Rooms;
use App\Entity\Scheduling;
use App\Entity\Server;
use App\Entity\User;
use App\Form\Type\NewMemberType;
use App\Form\Type\RoomType;
use App\Service\SchedulingService;
use App\Service\ServerUserManagment;
use App\Service\UserService;
use App\Service\InviteService;

use App\Service\RoomService;
use phpDocumentor\Reflection\Types\This;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class RoomController extends AbstractController
{
    private $translator;
    private $userService;
    private $serverUserManagment;
    public function __construct(TranslatorInterface $translator,UserService $userService,ServerUserManagment $serverUserManagment)
    {
        $this->translator = $translator;
        $this->userService = $userService;
        $this->serverUserManagment = $serverUserManagment;
    }

    /**
     * @Route("/room/new", name="room_new")
     */
    public function newRoom(SchedulingService $schedulingService, Request $request)
    {
        if ($request->get('id')) {
            $room = $this->getDoctrine()->getRepository(Rooms::class)->findOneBy(array('id' => $request->get('id')));
            if ($room->getModerator() !== $this->getUser()) {
                return $this->redirectToRoute('dashboard', ['snack' => $this->translator->trans('Keine Berechtigung')]);
            }
            $snack = $this->translator->trans('Konferenz erfolgreich bearbeitet');
            $title = $this->translator->trans('Konferenz bearbeiten');
            $sequence = $room->getSequence() + 1;
            $room->setSequence($sequence);
            if (!$room->getUidModerator()) {
                $room->setUidModerator(md5(uniqid('h2-invent', true)));
            }
            if (!$room->getUidParticipant()) {
                $room->setUidParticipant(md5(uniqid('h2-invent', true)));
            }

        } else {
            $room = new Rooms();
            $room->addUser($this->getUser());
            $room->setDuration(60);
            $room->setUid(rand(01, 99) . time());
            $room->setModerator($this->getUser());
            $room->setSequence(0);
            $room->setUidReal(md5(uniqid('h2-invent', true)));
            $room->setUidModerator(md5(uniqid('h2-invent', true)));
            $room->setUidParticipant(md5(uniqid('h2-invent', true)));

            $snack = $this->translator->trans('Konferenz erfolgreich erstellt');
            $title = $this->translator->trans('Neue Konferenz erstellen');
        }
        $servers = $this->serverUserManagment->getServersFromUser($this->getUser());


        $form = $this->createForm(RoomType::class, $room, ['server' => $servers, 'action' => $this->generateUrl('room_new', ['id' => $room->getId()])]);
        if ($request->get('id')) {
            $form->remove('scheduleMeeting');
        }
        try {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                $room = $form->getData();
                if(!$room->getStart()){
                    $snack = $this->translator->trans('Fehler, Bitte kontrollieren Sie ihre Daten.');
                    return $this->redirectToRoute('dashboard', array('snack' => $snack, 'color' => 'danger'));
                }
                $room->setEnddate((clone $room->getStart())->modify('+ ' . $room->getDuration() . ' minutes'));
                $em = $this->getDoctrine()->getManager();
                $em->persist($room);
                $em->flush();
                $schedulingService->createScheduling($room);

                if ($request->get('id')) {
                    foreach ($room->getUser() as $user) {
                        $this->userService->editRoom($user, $room);
                    }
                } else {
                    $this->userService->addUser($room->getModerator(), $room);
                }
                $modalUrl = base64_encode($this->generateUrl('room_add_user', array('room' => $room->getId())));
                if ($room->getScheduleMeeting()) {
                    $modalUrl = base64_encode($this->generateUrl('schedule_admin', array('id' => $room->getId())));
                }
                return $this->redirectToRoute('dashboard', ['snack' => $snack, 'modalUrl' => $modalUrl]);


            }
        } catch (\Exception $e) {
            $snack = $this->translator->trans('Fehler, Bitte kontrollieren Sie ihre Daten.');
            return $this->redirectToRoute('dashboard', array('snack' => $snack, 'color' => 'danger'));
        }
        return $this->render('base/__newRoomModal.html.twig', array('form' => $form->createView(), 'title' => $title));
    }

    /**
     * @Route("/room/add-user", name="room_add_user")
     */
    public function roomAddUser(Request $request, InviteService $inviteService)
    {
        $newMember = array();
        $room = $this->getDoctrine()->getRepository(Rooms::class)->findOneBy(['id' => $request->get('room')]);
        if ($room->getModerator() !== $this->getUser()) {
            return $this->redirectToRoute('dashboard', ['snack' => 'Keine Berechtigung']);
        }
        $form = $this->createForm(NewMemberType::class, $newMember, ['action' => $this->generateUrl('room_add_user', ['room' => $room->getId()])]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {


            $newMembers = $form->getData();
            $lines = explode("\n", $newMembers['member']);

            if (!empty($lines)) {
                $em = $this->getDoctrine()->getManager();
                $falseEmail = array();
                foreach ($lines as $line) {
                    $newMember = trim($line);
                    if (filter_var($newMember, FILTER_VALIDATE_EMAIL)) {
                        $user = $inviteService->newUser($newMember);
                        $user->addRoom($room);
                        $user->addAddressbookInverse($room->getModerator());
                        $em->persist($user);
                        $snack = $this->translator->trans("Teilnehmer wurden eingeladen");
                        $this->userService->addUser($user, $room);
                    } else {
                        $falseEmail[] = $newMember;
                        $emails = implode(", ", $falseEmail);
                        $snack = $this->translator->trans("Einige Teilnehmer eingeladen. {emails} ist/sind nicht korrekt und können nicht eingeladen werden", array('{emails}' => $emails));
                    }
                }
                $em->flush();
                return $this->redirectToRoute('dashboard', ['snack' => $snack]);
            }
        }
        $title = $this->translator->trans('Teilnehmer verwalten');

        return $this->render('room/attendeeModal.twig', array('form' => $form->createView(), 'title' => $title, 'room' => $room));
    }

    /**
     * @Route("/room/join/{t}/{room}", name="room_join")
     * @ParamConverter("room", options={"mapping"={"room"="id"}})
     */
    public
    function joinRoom(RoomService $roomService, Rooms $room, $t)
    {

        if (in_array($this->getUser(), $room->getUser()->toarray())) {
            $url = $roomService->join($room, $this->getUser(), $t, $this->getUser()->getFirstName() . ' ' . $this->getUser()->getLastName());
            return $this->redirect($url);
        }

        return $this->redirectToRoute('dashboard', ['join_room' => $room->getId(), 'type' => $t]);
    }

    /**
     * @Route("/room/user/remove", name="room_user_remove")
     */
    public
    function roomUserRemove(Request $request)
    {

        $room = $this->getDoctrine()->getRepository(Rooms::class)->findOneBy(['id' => $request->get('room')]);
        $user = $this->getDoctrine()->getRepository(User::class)->findOneBy(['id' => $request->get('user')]);
        $snack = 'Keine Berechtigung';
        if ($room->getModerator() === $this->getUser() || $user === $this->getUser()) {
            $room->removeUser($user);
            $em = $this->getDoctrine()->getManager();
            $em->persist($room);
            $em->flush();
            $snack = $this->translator->trans('Teilnehmer gelöscht');
            $this->userService->removeRoom($user, $room);
        }

        return $this->redirectToRoute('dashboard', ['snack' => $snack]);
    }

    /**
     * @Route("/room/remove", name="room_remove")
     */
    public
    function roomRemove(Request $request)
    {

        $room = $this->getDoctrine()->getRepository(Rooms::class)->findOneBy(['id' => $request->get('room')]);
        $snack = 'Keine Berechtigung';
        if ($this->getUser() === $room->getModerator()) {
            $em = $this->getDoctrine()->getManager();
            foreach ($room->getUser() as $user) {
                $this->userService->removeRoom($user, $room);
                $room->removeUser($user);
                $em->persist($room);
            }
            $room->setModerator(null);
            $em->persist($room);
            $em->flush();
            $snack = $this->translator->trans('Konferenz gelöscht');
        }
        return $this->redirectToRoute('dashboard', ['snack' => $snack]);
    }

    /**
     * @Route("/room/clone", name="room_clone")
     */
    public
    function roomClone(Request $request, TranslatorInterface $translator, SchedulingService $schedulingService)
    {

        $roomOld = $this->getDoctrine()->getRepository(Rooms::class)->find($request->get('room'));
        $room = clone $roomOld;
        // here we clean all the scheduls from the old room
        foreach ($room->getSchedulings() as $data) {
            $room->removeScheduling($data);
        }
        $room->setUid(rand(01, 99) . time());
        $room->setSequence(0);

        $snack = $translator->trans('Keine Berechtigung');
        $title = $translator->trans('Konferenz duplizieren');

        if ($this->getUser() === $room->getModerator()) {

            $servers = $this->serverUserManagment->getServersFromUser($this->getUser());
            $form = $this->createForm(RoomType::class, $room, ['server' => $servers, 'action' => $this->generateUrl('room_clone', ['room' => $room->getId()])]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $room = $form->getData();
                $room->setUidReal(md5(uniqid('h2-invent', true)));
                $room->setEnddate((clone $room->getStart())->modify('+ ' . $room->getDuration() . ' minutes'));
                $em = $this->getDoctrine()->getManager();
                $em->persist($room);
                $em->flush();

                $schedulingService->createScheduling($room);
                foreach ($roomOld->getUser() as $user) {
                    $this->userService->addUser($user, $room);
                }
                $snack = $translator->trans('Teilnehmer bearbeitet');
                return $this->redirectToRoute('dashboard', ['snack' => $snack, 'modalUrl' => base64_encode($this->generateUrl('room_add_user', array('room' => $room->getId())))]);
            }
            return $this->render('base/__newRoomModal.html.twig', array('form' => $form->createView(), 'title' => $title));
        }

        return $this->redirectToRoute('dashboard', ['snack' => $snack]);
    }
    function getRoom($id= null){

    }
}
