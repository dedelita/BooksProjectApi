<?php

namespace App\Controller;
//require_once 'vendor/autoload.php';

use App\Entity\Book;
use App\Repository\BookRepository;
use App\Repository\UserRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

use function Symfony\Component\String\u;

class BookController extends AbstractController
{
    private $bookRepository;
    private $client;
    
    public function __construct(BookRepository $bookRepository)
    {
        $this->client = new \Google\Client();
        $this->bookRepository = $bookRepository;
    }

    /**
     * @Route("/testGApi", name="test_gapi", methods="GET")
     */
    public function testGApi() 
    {
    
        $this->client->setApplicationName($this->getParameter("app_name"));
        $this->client->setDeveloperKey($this->getParameter("api_key"));

        $title = "harry potter et le prince de sang mélé";
        $author = "Rowling";
        $isbn = "";
        $q = "";
        if($title)
            $q.= "intitle:$title";
        if($author)
            $q.= " inauthor:$author";
        if($isbn)
            $q.= " isbn:$isbn";
        
        $service = new \Google_Service_Books($this->client);
        $optParams = array(
        'q' => $q,
        );
        $results = $service->volumes->listVolumes($optParams, []);
            $books = [];
        foreach ($results->getItems() as $item) {
            $book["title"] = $item['volumeInfo']["title"];
            $book["subtitle"] = $item['volumeInfo']["subtitle"];
            $book["author"] = $item['volumeInfo']["authors"];
            foreach($item['volumeInfo']["industryIdentifiers"] as $identifier) {
                if($identifier["type"] == "ISBN_13") {
                    $book["isbn"] = $identifier["identifier"];
                }
            }
            $books[] = $book;
        }
        return $this->json($books);
    }
   
    /**
     * @return id of new or corresponding book
     */
    public function add($title, $author, $genre) : JsonResponse
    {
        $book = $this->bookRepository->findBook($title, $author);
        if(!$book) {
            $book = $this->bookRepository->add($author, $title, $genre);
        }
        $encoders = [new JsonEncoder()]; $defaultContext = [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object, $format, $context) {
                return ["id" => $object->getId(),];
            },
        ];
        $normalizers = [new ObjectNormalizer(null, null, null, null, null, null, $defaultContext)];
        $serializer = new Serializer($normalizers, $encoders);

        return new JsonResponse($book->getId(), Response::HTTP_OK);
    }
}
