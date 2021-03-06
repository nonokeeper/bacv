<?php

namespace App\Controller;

use App\Entity\Reponse;
use App\Entity\User;
use App\Repository\QuestionRepository;
use App\Repository\ReponseRepository;
use App\Repository\SondageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class ReponseController extends AbstractController
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var Security
     */
    private $security;

    /**
     * @var SondageRepository
     */
    private $sondageRep;

    /**
     * @var QuestionRepository
     */
    private $questionRep;

    /**
     * @var ReponseRepository
     */
    private $reponseRep;

    public function __construct(EntityManagerInterface $em, Security $security,
        SondageRepository $sondageRep, QuestionRepository $questionRep, ReponseRepository $reponseRep)
    {
        $this->em = $em;
        $this->security = $security;
        $this->sondageRep = $sondageRep;
        $this->questionRep = $questionRep;
        $this->reponseRep = $reponseRep;
    }
    /**
     * @Route("/reponses", name="reponses")
     */
    public function index()
    {
        $sondages = $this->sondageRep->findAll();
        return $this->render('reponse/index.html.twig', [
            'sondages' => $sondages
        ]);
    }

    /**
     * @Route("/reponse/{id}", name="reponse.view")
     */
    public function view($id)
    {
        $sondage = $this->sondageRep->find($id);
        $reponses = $this->reponseRep->findBy(['sondage' => $id]);
        $questions = $sondage->getQuestions();
        $users = new ArrayCollection();
        $olduser = '';
        foreach($reponses as $rep)
        {
            $user = $rep->getUser();
            if ($olduser !== $user)
                $users->add($user);
            $olduser=$user;
        }

        return $this->render('reponse/view.html.twig', [
            'sondage'   => $sondage,
            'reponses'  => $reponses,
            'questions' => $questions,
            'users'     => $users
        ]);
    }

    /**
     * @Route("/validationReponse", name="reponse.validation")
     */
    public function validate(Request $request)
    {
        $sondageId = $request->request->get('sondage');
        $cpt = $request->request->get('cpt');
        $questionId = $request->request->get('question');
        $reponseLabel = $request->request->get('choix');
        if (!is_array($reponseLabel)) {
            $reponseLabel = array($reponseLabel);
        }
        $user = $this->security->getUser();
        $sondage = $this->sondageRep->find($sondageId);
        $question = $this->questionRep->find($questionId);
        $reponse = $this->reponseRep->findOneBy(['sondage' => $sondage, 'question' => $question, 'user' => $user]);

        if ($reponse) { // Mise à jour
            $reponse->setChoix($reponseLabel);
            $this->em->flush();
        } else { // Création
            $reponse = new Reponse;
            $reponse->setSondage($sondage);
            $reponse->setQuestion($question);
            $reponse->setUser($user);
            $reponse->setvalidatedAt(new \DateTime("now"));
            $reponse->setChoix($reponseLabel);
            $this->em->persist($reponse);
            $this->em->flush();
        }
        
        // Passage à la question suivante s'il y en a
        return $this->redirectToRoute('sondage.start', ['id' => $sondageId, 'cpt' => $cpt+1]);
    }
}
