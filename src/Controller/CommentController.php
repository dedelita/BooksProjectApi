<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\BookRepository;
use App\Repository\CommentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @Route("{id_user}/{id_book}")
 */
class CommentController extends AbstractController
{
    private $commentRepository;

    public function __construct(CommentRepository $commentRepository)
    {
        $this->commentRepository = $commentRepository;
    }
    
    /**
     * @Route("/addComment", name="add_comment", methods="POST")
     */
    public function add(Request $request, UserRepository $userRepository, BookRepository $bookRepository): JsonResponse
    {
        if(!$this->getUser()) {
            return new JsonResponse(["success" => 0, "message" => "Utilisateur non connecté!"], Response::HTTP_BAD_REQUEST);
        }
        
        if($this->getUser()->getId() != $request->get('id_user')) {
            return new JsonResponse(["success" => 0, "message" => "Mauvais utilisateur!"], Response::HTTP_CREATED);
        }
        $encoders = [new JsonEncoder()];
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $defaultContext = [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object, $format, $context) {
                return ["id" => $object->getId()];
            },
        ];
        $normalizers = [new ObjectNormalizer($classMetadataFactory, null, null, null, null, null, $defaultContext)];
        $serializer = new Serializer($normalizers, $encoders);

        $idUser = $request->get('id_user');
        $idBook = $request->get('id_book');

        $content = $request->get('content');
        $stars = $request->get('stars');
        $user = $userRepository->find($idUser);
        $book = $bookRepository->find($idBook);
        $date = new \DateTime();
        if ($user && $book) {
            $comment = $this->commentRepository->findOneBy(["writer" => $idUser, "book" => $idBook]);

            if (!$comment) {
                $comment = $this->commentRepository->add($date, $content, $stars, $user, $book);
            } else {
                $comment->setDate($date);
                $comment->setContent($content);
                $comment->setStars($stars);
                $this->commentRepository->save($comment);
            }
        }
        $json_comment = $serializer->serialize($comment, "json", ["groups" => ["infos_for_comment"]]);
        return new JsonResponse(["success" => 1, "comment" => $json_comment], Response::HTTP_CREATED);
    }
}
