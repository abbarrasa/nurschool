<?php

namespace Nurschool\Command;

use Doctrine\DBAL\Exception as DatabaseException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Nurschool\Entity\User;
use Nurschool\Repository\RoleRepository;
use Nurschool\Repository\UserRepository;
use Nurschool\Validation\SuperAdminCredentialsValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create-super-admin', description: 'Interactively create a user with the ROLE_SUPER_ADMIN role.')]
final class CreateSuperAdminCommand extends Command
{
    private const string ROLE_SUPER_ADMIN_NAME = 'ROLE_SUPER_ADMIN';

    public function __construct(
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SuperAdminCredentialsValidator $credentials,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$input->isInteractive()) {
            $io->error('This command requires interaction. Run it without --no-interaction in a terminal.');

            return Command::INVALID;
        }

        try {
            $emailQuestion = (new Question('Email'))->setValidator($this->credentials->email(...))->setMaxAttempts(3);
            $email = (string) $io->askQuestion($emailQuestion);
            if ($this->users->findOneBy(['email' => $email]) !== null) {
                $io->error('A user with this email already exists. The account has not been modified.');

                return Command::FAILURE;
            }

            $passwordQuestion = $this->secretQuestion('Password (at least 12 characters, at most 72 bytes)');
            $passwordQuestion->setValidator($this->credentials->password(...));
            $password = (string) $io->askQuestion($passwordQuestion);

            $confirmation = $this->secretQuestion('Repeat the password');
            $confirmation->setValidator(static function (#[\SensitiveParameter] mixed $value) use ($password): string {
                if (!is_string($value) || !hash_equals($password, $value)) {
                    throw new \InvalidArgumentException('The passwords do not match.');
                }

                return $value;
            });
            $io->askQuestion($confirmation);

            $user = (new User())->setEmail($email);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            unset($password);

            $role = $this->roles->findOneBy(['name' => self::ROLE_SUPER_ADMIN_NAME]);
            if ($role === null) {
                $io->error('The super administrator role does not exist.');

                return Command::FAILURE;
            }
            $user->addRole($role);
            $this->entityManager->persist($user);
            // Doctrine flushes the role, user and join table in a single transaction.
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            $io->error('The email or role was created concurrently. The user was not created; please try again.');

            return Command::FAILURE;
        } catch (DatabaseException) {
            $io->error('Could not create the user. Check the database connection and migrations.');

            return Command::FAILURE;
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success('User created successfully with the ROLE_SUPER_ADMIN role.');

        return Command::SUCCESS;
    }

    private function secretQuestion(string $prompt): Question
    {
        return (new Question($prompt))
            ->setHidden(true)
            ->setHiddenFallback(false)
            ->setTrimmable(false)
            // Remove the terminal line ending while preserving password whitespace.
            ->setNormalizer(static fn (mixed $value): mixed => is_string($value) ? rtrim($value, "\r\n") : $value)
            ->setMaxAttempts(3);
    }
}
