<?php

declare(strict_types=1);

return [
    'document' => 'thinkos.pdf',
    'sourceCommit' => 'da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34',
    'markerPath' => 'data/examples/marker/thinkos.md',
    'referencePath' => 'data/examples/nougat/thinkos.md',
    'referenceKind' => 'committed-nougat-output-surrogate',
    'scoreThreshold' => 0.95,
    'chunkLength' => 500,
    'markerExcerpt' => <<<'MARKER'
## Preface

In many computer science programs, Operating Systems is an advanced topic.

By the time students take it, they know how to program in C, and they have probably taken a class in Computer Architecture. Usually the goal of the class is to expose students to the design and implementation of operating systems, with the implied assumption that some of them will do research in this area, or write part of an OS.

This book is intended for a different audience, and it has different goals. I
developed it for a class at Olin College called Software Systems. Most students taking this class learned to program in Python, so one of the goals is to help them learn C. For that part of the class, I use Griffiths and Griffiths, *Head First C*, from O'Reilly Media. This book is meant to complement that one. Few of my students will ever write an operating system, but many of them will write low-level applications in C or work on embedded systems. My class includes material from operating systems, networks, databases, and embedded systems, but it emphasizes the topics programmers need to know. This book does not assume that you have studied Computer Architecture. As we go along, I will explain what we need. If this book is successful, it should give you a better understanding of what is happening when programs run, and what you can do to make them run better and faster.

Chapter 1 explains some of the differences between compiled and interpreted languages, with some insight into how compilers work. Recommended reading:
Head First C Chapter 1. Chapter 2 explains how the operating system uses processes to protect running programs from interfering with each other. Chapter 3 explains virtual memory and address translation. Recommended reading: *Head First C* Chapter 2.

Chapter 4 is about file systems and data streams. Recommended reading:
Head First C Chapter 3.
MARKER,
    'referenceExcerpt' => <<<'REFERENCE'
## Preface

In many computer science programs, Operating Systems is an advanced topic. By the time students take it, they know how to program in C, and they have probably taken a class in Computer Architecture. Usually the goal of the class is to expose students to the design and implementation of operating systems, with the implied assumption that some of them will do research in this area, or write part of an OS.

This book is intended for a different audience, and it has different goals. I developed it for a class at Olin College called Software Systems.

Most students taking this class learned to program in Python, so one of the goals is to help them learn C. For that part of the class, I use Griffiths and Griffiths, _Head First C_, from O'Reilly Media. This book is meant to complement that one.

Few of my students will ever write an operating system, but many of them will write low-level applications in C or work on embedded systems. My class includes material from operating systems, networks, databases, and embedded systems, but it emphasizes the topics programmers need to know.

This book does not assume that you have studied Computer Architecture. As we go along, I will explain what we need.

If this book is successful, it should give you a better understanding of what is happening when programs run, and what you can do to make them run better and faster.

Chapter 1 explains some of the differences between compiled and interpreted languages, with some insight into how compilers work. Recommended reading: _Head First C_ Chapter 1.

Chapter 2 explains how the operating system uses processes to protect running programs from interfering with each other.

Chapter 3 explains virtual memory and address translation. Recommended reading: _Head First C_ Chapter 2.

Chapter 4 is about file systems and data streams. Recommended reading: _Head First C_ Chapter 3.
REFERENCE,
];
