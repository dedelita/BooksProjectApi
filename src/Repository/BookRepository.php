<?php

namespace App\Repository;

use App\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @method Book|null find($id, $lockMode = null, $lockVersion = null)
 * @method Book|null findOneBy(array $criteria, array $orderBy = null)
 * @method Book[]    findAll()
 * @method Book[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    public function save($book) {
        $this->_em->persist($book);
        $this->_em->flush();
    }

    public function add($author, $title, $genre) {
        $book = new Book();
        $book->setAuthor($author);
        $book->setTitle($title);
        $book->setGenre($genre);
        $this->save($book);

        return $book;
    }

    public function delete($book) {
        $this->_em->remove($book);
        $this->_em->flush();
    }

    public function findBook($title, $author) {
        return $this->createQueryBuilder('b')
            ->andWhere('b.title = :title AND b.author = :author')
            ->setParameter('title', $title)
            ->setParameter('author', $author)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deleteByIdUser($idUser) {
        $books = $this->findByIdUser($idUser);

        foreach($books as $book) {
            $this->delete($book);
        }
            
    }

    // /**
    //  * @return Book[] Returns an array of Book objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('b.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Book
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}