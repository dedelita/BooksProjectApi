<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\BookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;


class UserController extends AbstractController
{
    private $userRepository;
    
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }
        
    /**
     * @Route("/login", name="api_login", methods={"POST"})
     */
    public function login()
    {
    }
    /**
     * @Route("/logout", name="api_logout")
     */
    public function logout()
    {
    }
    /**
     * @Route("/", name="api_home")
     */
    public function home()
    {
    return $this->json(['result' => "home"]);
    }
    
    /**
    * @Route("/profile", name="api_profile")
    * @IsGranted("ROLE_USER")
    */
    public function profile()
    {
        return $this->json([
            'user' => $this->getUser()
        ]);
    }

    /**
     * @Route("/register", name="add_user", methods="POST")
     */
    public function add(Request $request, UserPasswordEncoderInterface $passwordEncoder): JsonResponse
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer($classMetadataFactory)];
        $serializer = new Serializer($normalizers, $encoders);
        $reponse = [];

        $username = $request->get("username");
        $email = $request->get("email");
        $password = $request->get("password");
        $confPassword = $request->get("confPassword");

        if (empty($username) || empty($email) || empty($password)) {
            return new JsonResponse(["success" => 0, "message" => "Les paramètres ne doivent pas être vide"], 400);
        }

        $us = $this->userRepository->findOneByUsername($username);
        if($us) {
            return new JsonResponse(["success" => 0, "message" => "Username déjà utilisé"], 400);
        }
        $us = $this->userRepository->findOneByEmail($email);
        if($us) {
            return new JsonResponse(["success" => 0, "message" => "Email déjà utilisé"], 400);
        } else {
            if(hash_equals($password, $confPassword)) {
                $user = new User();
                $encodedPassword = $passwordEncoder->encodePassword($user, $password);
                
                $user = $this->userRepository->add($username, $email, $encodedPassword);
                $json_user= $serializer->serialize($user, 'json', ['groups' => ["infos"]]);
                return new JsonResponse(["success" => 1, "user" => $json_user], Response::HTTP_CREATED);
            } else {
                return new JsonResponse(["success" => 0, "message" => "Les mots de passe ne correspondent pas"], 400);
            }
        }
        return new JsonResponse($reponse, 400);        
    }

    /**
     * @Route("/deleteUser/{id}", name="delete_user", methods="DELETE")
     */
    public function delete(Request $request): JsonResponse
    {
        $id = $request->get("id");
        $user = $this->userRepository->findOneById($id);
        if($user) {
            $this->userRepository->delete($user);
        }

        return new JsonResponse(["success" => 1], Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/editUser/{id}", name="edit_user", methods="PUT")
    * @IsGranted("ROLE_USER")
     */
    public function edit(Request $request): JsonResponse
    {
        $user = $this->userRepository->findOneByEmail($request->get("email"));
        if($user && $user->getId() != $request->get("id")) {
            return new JsonResponse(["success" => 0, "message" => "Email déjà utilisé par un autre user."], 400);
        }
        $user = $this->userRepository->findOneByUsername($request->get("username"));
        if($user && $user->getId() != $request->get("id")) {
            return new JsonResponse(["success" => 0, "message" => "Username déjà utilisé par un autre user."], 400);
        }

        $user = $this->userRepository->find($request->get("id"));
        if($user) {
            if(sha1($request->get("password")) == $user->getPassword()) {
                $user->setUsername($request->get("username"));
                $user->setEmail($request->get("email"));
                $this->userRepository->save($user);
            } else {
                return new JsonResponse(["success" => 0, "message" => "Mot de passe incorrecte"], 400);
            }
        } else {
            return new JsonResponse(["success" => 0, "message" => "User incorrecte"], 400);
        }
        return new JsonResponse(["success" => 1, "user" => json_encode($user)], Response::HTTP_OK);
    }

    /**
     * @Route("/{id}/addBook", name="add_book", methods="POST")
     * @IsGranted("ROLE_USER")
     */
    public function addBook(Request $request, BookRepository $bookRepository) : JsonResponse
    {
        $id = $request->get("id");
        if(!$this->getUser()) {
            return new JsonResponse(["success" => 0, "message" => "Utilisateur non connecté!"], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->find($id);
        if($this->getUser() != $user) {
            return new JsonResponse(["success" => 0, "message" => "Mauvais utilisateur!"], Response::HTTP_BAD_REQUEST);
        }
        
        $title = $request->get('title');
        $author = $request->get('author');
        if(empty($title) || empty($author)) {
            return new JsonResponse(["success" => 0, "message" => "Les paramètres ne doivent pas être vide!"], Response::HTTP_BAD_REQUEST);
        }
        $resBook = $this->forward("App\Controller\BookController::add", [
            "title" => $title,
            "author" => $author,
            "genre" => $request->get('genre')
        ]);
        $book = $bookRepository->find($resBook->getContent());
        $user->addBook($book);
        $this->userRepository->save($user);

        return new JsonResponse(
            ["success" => 1, "message" => "$title ajouté à l'utilisateur $id"]
            , Response::HTTP_OK);
    }

    /**
     * @Route("/{id}/getBooks", name="get_books", methods="GET")
     * @IsGranted("ROLE_USER")
     */
    public function getBooks(Request $request): JsonResponse
    {
        $encoders = [new JsonEncoder()];
        $defaultContext = [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object, $format, $context) {
                return [
                        "id" => $object->getId(),
                    ];
            },
        ];
        $normalizers = [new ObjectNormalizer(null, null, null, null, null, null, $defaultContext)];
        $serializer = new Serializer($normalizers, $encoders);
        
        $user = $this->userRepository->find($request->get('id'));
        $books = $user->getBooks();

        $json_books = [];
        foreach($books as $book) {
           $json_books[] = $serializer->serialize($book, "json");
        }
        
        return new JsonResponse(["success" => 1, "books" => $json_books], Response::HTTP_OK);
    }

    /**
     * @Route("/{id}/removeBook/{idBook}", name="remove_book", methods="DELETE")
     * @IsGranted("ROLE_USER")
     */
    public function removeBook(Request $request) : JsonResponse
    {
        $user = $this->userRepository->find($request->get("id"));
        $book = $this->bookRepository->find($request->get("idBook"));
        $user->removeBook($book);
        $this->userRepository->save($user);

        return new JsonResponse(
            ["success" => 1, "message" => "$book->getTitle() supprimé de sa liste"]
            , Response::HTTP_OK);
    }


}
