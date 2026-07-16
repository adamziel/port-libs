<?php

declare(strict_types=1);

return [
    'document' => 'multicolcnn.pdf',
    'sourceCommit' => 'da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34',
    'markerPath' => 'data/examples/marker/multicolcnn.md',
    'referencePath' => 'data/examples/nougat/multicolcnn.md',
    'referenceKind' => 'committed-nougat-output-surrogate',
    'scoreThreshold' => 0.80,
    'chunkLength' => 500,
    'markerExcerpt' => <<<'MARKER'
# An Aggregated Multicolumn Dilated Convolution Network For Perspective-Free Counting

Diptodip Deb Georgia Institute of Technology diptodipdeb@gatech.edu Jonathan Ventura University of Colorado Colorado Springs jventura@uccs.edu

## Abstract

We propose the use of dilated filters to construct an aggregation module in a multicolumn convolutional neural network for perspective-free counting. Counting is a common problem in computer vision (e.g. traffic on the street or pedestrians in a crowd). Modern approaches to the counting problem involve the production of a density map via regression whose integral is equal to the number of objects in the image. However, objects in the image can occur at different scales (e.g. due to perspective effects) which can make it difficult for a learning agent to learn the proper density map. While the use of multiple columns to extract multiscale information from images has been shown before, our approach aggregates the multiscale information gathered by the multicolumn convolutional neural network to improve performance.

## 1. Introduction

Learning to count the number of objects in an image is a deceptively difficult problem with many interesting applications, such as surveillance, traffic monitoring and medical image analysis. In many of these application areas, the objects to be counted vary widely in appearance, size and shape, and labeled training data is typically sparse.
MARKER,
    'referenceExcerpt' => <<<'REFERENCE'
# An Aggregated Multicolumn Dilated Convolution Network

for Perspective-Free Counting

Diptodip Deb

Georgia Institute of Technology

diptodipdeb@gatech.edu

Jonathan Ventura

University of Colorado Colorado Springs

jventura@uccs.edu

###### Abstract

We propose the use of dilated filters to construct an aggregation module in a multicolumn convolutional neural network for perspective-free counting. Counting is a common problem in computer vision (e.g. traffic on the street or pedestrians in a crowd). Modern approaches to the counting problem involve the production of a density map via regression whose integral is equal to the number of objects in the image. However, objects in the image can occur at different scales (e.g. due to perspective effects) which can make it difficult for a learning agent to learn the proper density map. While the use of multiple columns to extract multiscale information from images has been shown before, our approach aggregates the multiscale information gathered by the multicolumn convolutional neural network to improve performance.

## 1 Introduction

Learning to count the number of objects in an image is a deceptively difficult problem with many interesting applications, such as surveillance, traffic monitoring and medical image analysis. In many of these application areas, the objects to be counted vary widely in appearance, size and shape, and labeled training data is typically sparse.
REFERENCE,
];
